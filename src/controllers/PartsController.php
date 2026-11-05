<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

class PartsController
{
    private PartsModel $partsModel;
    private ResponseFactory $responseFactory;

    public function __construct(
        PartsModel $partsModel,
        ResponseFactory $responseFactory
    ) {
        $this->partsModel = $partsModel;
        $this->responseFactory = $responseFactory;
    }

    // GET /parts
    public function index(Request $request, Response $response): Response
    {
        // $stmt = $pdo->query("SELECT * FROM parts");

        // $parts = $stmt->fetchAll();

        // $response->getBody()->write(
        //     json_encode($parts)
        // );

        // return $response
        //     ->withHeader('Content-Type', 'application/json');


        // GET category, brand_id and page from query parameter using _GET super-global variable
        // 1. Extract Pagination Params (with sensible defaults)
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 5);
        $name = $_GET['name'] ?? null;
        $category = $_GET['category'] ?? null;
        $brand_id = $_GET['brand_id'] ?? null;
        $sort = $_GET['sort'] ?? 'id';
        $direction = $_GET['direction'] ?? 'asc';
        $search = $_GET['search'] ?? null;

        // Invalid query parameters validation
        $allowedParams = [
            'page',
            'limit',
            'name',
            'category',
            'brand_id',
            'sort',
            'direction',
            'search'
        ];

        foreach ($_GET as $key => $value) {
            if (!in_array($key, $allowedParams)) {
                // $response->getBody()->write(
                //     json_encode(['error' => 'Invalid query parameter'])
                // );

                // return $response
                //     ->withStatus(400)
                //     ->withHeader('Content-Type', 'application/json');

                return sendError(
                    $this->responseFactory,
                    400,
                    'Invalid Query parameter'
                );
            }
        }

        // Pagination validation
        // Enforce boundaries (prevent negative numbers or massive limits)
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 5;
        if ($limit > 50) $limit = 50; // Max 50 items per page
        $offset = ($page - 1) * $limit;
        $brand_id = $brand_id !== null ? (int) $brand_id : null;

        // $page = (int)$page;
        // $limit = 5;

        if (!is_numeric($page) || $page < 1 || $page > 100) {
            // $response->getBody()->write(
            //     json_encode(['error' => 'Invalid pagination'])
            // );

            // return $response
            //     ->withStatus(400)
            //     ->withHeader('Content-Type', 'application/json');

            return sendError(
                $this->responseFactory,
                400,
                'Invalid pagination'
            );
        }

        // Brand ID validation
        if ($brand_id !== null && (!is_numeric($brand_id) || (int) $brand_id < 1)) {
            // $response->getBody()->write(
            //     json_encode(['error' => 'Invalid brand ID'])
            // );

            // return $response
            //     ->withStatus(400)
            //     ->withHeader('Content-Type', 'application/json');

            return sendError(
                $this->responseFactory,
                400,
                'Invalid brand ID'
            );
        }

        $allowedSortFields = ['id', 'name', 'category', 'brand_id', 'price'];
        $allowedDirections = ['asc', 'desc'];

        if (
            !in_array($sort, $allowedSortFields, true) ||
            !in_array($direction, $allowedDirections, true)
        ) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid sorting parameter'
            );
        }

        // 2. Build Dynamic Filtering (WHERE clauses)
        $whereClauses = [];
        $queryParams = [];

        if (isset($name) && trim($name) !== '') {
            $whereClauses[] = "name LIKE :name";
            $queryParams['name'] = "%{$name}%"; // String interpolation instead of "%" . $name . "%"
        }

        if (isset($category)) {
            $whereClauses[] = "category = :category";
            $queryParams['category'] = $category;
        }

        if (isset($brand_id)) {
            $whereClauses[] = "brand_id = :brand_id";
            $queryParams['brand_id'] = $brand_id;
        }

        // Filter logic for parts name and category with one search
        if (isset($search) && trim($search) !== '') {
            $whereClauses[] = "(name LIKE :nameSearch OR category LIKE :categorySearch)";
            $searchText = '%' . trim($search) . '%';
            $queryParams['nameSearch'] = $searchText;
            $queryParams['categorySearch'] = $searchText;
        }

        // Combine WHERE clauses if any exist
        $whereSql = "";
        if (count($whereClauses) > 0) {
            $whereSql = "WHERE " . implode(" AND ", $whereClauses);
        }
        // $whereSql = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // 3. Get Total Count (for metadata)s
        $totalItems = $this->partsModel->countParts(
            $whereSql,
            $queryParams
        );
        $totalPages = ceil($totalItems / $limit);

        // 4. Fetch the Paginated and sort Data
        $parts = $this->partsModel->getPartsPage(
            $whereSql,
            $queryParams,
            $limit,
            $offset,
            $sort,
            $direction
        );

        // 5. Return Enveloped Response
        $response->getBody()->write(
            json_encode([
                "metadata" => [
                    "total_items" => $totalItems,
                    "total_pages" => $totalPages,
                    "current_page" => $page,
                    "per_page" => $limit
                ],
                "data" => $parts
            ])
        );

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // GET /parts/{id}
    public function show(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $partId = $args['id'];
        $part = $this->partsModel->findPart($partId);

        if (!$part) {
            // $response->getBody()->write(
            //     json_encode([
            //         'error' => 'PC part not found'
            //     ])
            // );

            // return $response
            //     ->withStatus(404)
            //     ->withHeader('Content-Type', 'application/json');

            return sendError(
                $this->responseFactory,
                404,
                'PC part not found'
            );
        }

        $response->getBody()->write(
            json_encode($part)
        );

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // POST /parts
    public function store(Request $request, Response $response): Response
    {
        // $input = json_decode(
        //     $request->getBody()->getContents(),
        //     true
        // );

        $input = $request->getParsedBody();

        // Malformed JSON or no JSON object
        if (!is_array($input)) {
            return sendError(
                $this->responseFactory,
                400,
                'Malformed JSON payload.'
            );
        }

        // Valid JSON, but fields failed validation
        $validationErrors = validatePartsInput($input);

        if (!empty($validationErrors)) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                $validationErrors
            );
        }

        // Save trimmed text values
        $id = $this->partsModel->createPart($input);

        $response->getBody()->write(json_encode([
            'message' => 'New PC part added',
            'id' => $id,
        ]));

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    // DELETE /parts/{id}
    public function delete(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $partId = $args['id'];
        $deletedRows = $this->partsModel->deletePart($partId);

        if ($deletedRows === 0) {
            // $response->getBody()->write(
            //     json_encode(['error' => 'PC part not found'])
            // );

            // return $response
            //     ->withStatus(404)
            //     ->withHeader('Content-Type', 'application/json');

            return sendError(
                $this->responseFactory,
                404,
                'PC part not found'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'PC part removed successfully'])
        );

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // PATCH /parts/{id}
    public function patch(
        Request $request,
        Response $response,
        array $args
    ): Response {
        // $data = json_decode($request->getBody()->getContents(), true);

        $input = $request->getParsedBody();

        // if (!isset($input['price'])) {
        //     $response->getBody()->write(
        //         json_encode(['error' => 'Missing price field'])
        //     );

        //     return $response
        //         ->withStatus(400)
        //         ->withHeader('Content-Type', 'application/json');
        // }


        if (!is_array($input)) {
            return sendError(
                $this->responseFactory,
                400,
                'Malformed JSON payload.'
            );
        }

        if (!isset($input['price'])) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['price' => 'Price is required.']
            );
        }

        $updatedRows = $this->partsModel->updatePartPrice(
            $args['id'],
            $input['price']
        );

        if ($updatedRows === 0) {
            // $response->getBody()->write(
            //     json_encode(['error' => 'PC part not found'])
            // );

            // return $response
            //     ->withStatus(404)
            //     ->withHeader('Content-Type', 'application/json');

            return sendError(
                $this->responseFactory,
                404,
                'PC part not found'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'PC part\'s price updated successfully'])
        );

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // PUT /parts/{id}
    public function replace(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $input = $request->getParsedBody();
        $partId = $args['id'];

        // Check if JSON decoding failed completely
        if (!is_array($input)) {
            return sendError(
                $this->responseFactory,
                400,
                "Malformed JSON payload."
            );
        }

        // Run validation
        $validationErrors = validatePartsInput($input);

        if (!empty($validationErrors)) {
            return sendError(
                $this->responseFactory,
                422,
                "Validation Failed",
                $validationErrors
            );
        }

        // Check if the PC_part id exists
        $part = $this->partsModel->findPartId($partId);

        if (empty($part)) {
            return sendError(
                $this->responseFactory,
                404,
                "PC part not found"
            );
        }

        // Update parts based on inputs
        $this->partsModel->replacePart($partId, $input);

        // Fetch updated part data using GET method
        $updatedPart = $this->partsModel->findPart($partId);

        // Return 200 code with updated book data
        $response->getBody()->write(json_encode($updatedPart));

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // Combine the database price with an external exchange rate
    public function convertedPrice(Request $request, Response $response, array $args): Response{
        
        // Validate the part ID
        $partId = filter_var($args['id'], FILTER_VALIDATE_INT);

        if ($partId === false || $partId < 1) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid part ID'
            );
        }

        // Accept only the currency query parameter
        $params = $request->getQueryParams();

        foreach ($params as $key => $value) {
            if ($key !== 'currency') {
                return sendError(
                    $this->responseFactory,
                    400,
                    'Invalid query parameter'
                );
            }
        }

        $currency = $params['currency'] ?? 'USD';

        if (
            !is_string($currency) ||
            !in_array($currency, ['USD', 'EUR', 'GBP'], true)
        ) {
            return sendError(
                $this->responseFactory,
                400,
                'Currency must be USD, EUR or GBP'
            );
        }

        // Get the original CAD price from our database
        $part = $this->partsModel->findPart($args['id']);

        if (!$part) {
            return sendError(
                $this->responseFactory,
                404,
                'PC part not found'
            );
        }

        // Get the external exchange rate
        $exchangeService = new ExchangeRateService();
        $exchange = $exchangeService->getRate($currency);

        if ($exchange === false) {
            return sendError(
                $this->responseFactory,
                502,
                'Exchange rate service unavailable. Please try again later.'
            );
        }

        // Calculate the converted price without changing the database
        $convertedPrice = (float) $part['price'] * $exchange['rate'];

        $response->getBody()->write(
            json_encode([
                'id' => $part['id'],
                'name' => $part['name'],
                'original_currency' => 'CAD',
                'original_price' => $part['price'],
                'target_currency' => $currency,
                'exchange_rate' => $exchange['rate'],
                'converted_price' => number_format(
                    $convertedPrice,
                    2,
                    '.',
                    ''
                ),
                'rate_date' => $exchange['date'],
                'rate_source' => 'Frankfurter'
            ])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

}
