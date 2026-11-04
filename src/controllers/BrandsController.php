<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

class BrandsController
{
    private BrandsModel $brandsModel;
    private ResponseFactory $responseFactory;

    public function __construct(
        BrandsModel $brandsModel,
        ResponseFactory $responseFactory
    ) {
        $this->brandsModel = $brandsModel;
        $this->responseFactory = $responseFactory;
    }

    // GET /brands
    public function index(Request $request, Response $response): Response
    {
        // $stmt = $pdo->query("SELECT * FROM brands");

        // $brands = $stmt->fetchAll();

        // $response->getBody()->write(
        //     json_encode($brands)
        // );

        // return $response
        //     ->withHeader('Content-Type', 'application/json');

        // 1. Extract Pagination Params (with sensible defaults)
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 5);
        $name = $_GET['name'] ?? null;

        // Invalid query parameters validation
        $allowedParams = ['page', 'limit', 'name'];

        foreach ($_GET as $key => $value) {
            if (!in_array($key, $allowedParams)) {
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

        // $page = (int)$page;
        // $limit = 5;

        if (!is_numeric($page) || $page < 1 || $page > 100) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid pagination'
            );
        }

        // 2. Build Dynamic Filtering (WHERE clauses)
        $whereClauses = [];
        $queryParams = [];

        if (!empty($name)) {
            $whereClauses[] = "name LIKE :name";
            $queryParams['name'] = "%" . $name . "%";
        }

        // Combine WHERE clauses if any exist
        $whereSql = "";
        if (count($whereClauses) > 0) {
            $whereSql = "WHERE " . implode(" AND ", $whereClauses);
        }

        // 3. Get Total Count (for metadata)s
        $totalItems = $this->brandsModel->countBrands(
            $whereSql,
            $queryParams
        );
        $totalPages = ceil($totalItems / $limit);

        // 4. Fetch the Paginated Data
        $parts = $this->brandsModel->getPaginatedBrands(
            $whereSql,
            $queryParams,
            $limit,
            $offset
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

    // GET /brands/{id}
    public function show(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $brandId = $args['id'];
        $brand = $this->brandsModel->findBrand($brandId);

        if (!$brand) {
            return sendError(
                $this->responseFactory,
                404,
                'PC brand not found'
            );
        }

        $response->getBody()->write(
            json_encode($brand)
        );

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // POST /brands
    public function store(Request $request, Response $response): Response
    {
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
        $validationErrors = validateBrandsInput($input);

        if (!empty($validationErrors)) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                $validationErrors
            );
        }

        // Save trimmed text values
        $id = $this->brandsModel->createBrand($input);

        $response->getBody()->write(
            json_encode([
                'message' => 'New PC brand added',
                'id' => $id
            ])
        );

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    // DELETE /brands/{id}
    public function delete(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $brandId = $args['id'];
        $deletedRows = $this->brandsModel->deleteBrand($brandId);

        if ($deletedRows === 0) {
            return sendError(
                $this->responseFactory,
                404,
                'PC brand not found'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'Brand removed successfully'])
        );

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // PATCH /brands/{id}
    public function patch(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $input = $request->getParsedBody();

        $brandId = $args['id'];

        // if (!isset($input['name'])) {
        //     $response->getBody()->write(
        //         json_encode(['error' => 'Missing name field'])
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

        if (!isset($input['name'])) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['name' => 'Name is required.']
            );
        }

        $updatedRows = $this->brandsModel->updateBrandName(
            $brandId,
            $input['name']
        );

        if ($updatedRows === 0) {
            return sendError(
                $this->responseFactory,
                404,
                'PC brand not found'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'Brand name updated successfully'])
        );

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }

    // PUT /brands/{id}
    public function replace(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $input = $request->getParsedBody();
        $brandId = $args['id'];

        // Check if JSON decoding failed completely
        if (!is_array($input)) {
            return sendError(
                $this->responseFactory,
                400,
                "Malformed JSON payload."
            );
        }

        // Run validation
        $validationErrors = validateBrandsInput($input);

        if (!empty($validationErrors)) {
            return sendError(
                $this->responseFactory,
                422,
                "Validation Failed",
                $validationErrors
            );
        }

        // Check if the PC_part id exists
        $part = $this->brandsModel->findBrandId($brandId);

        if (empty($part)) {
            return sendError(
                $this->responseFactory,
                404,
                "PC brand not found"
            );
        }

        // Update parts based on inputs
        $this->brandsModel->replaceBrand($brandId, $input);

        // Fetch updated part data using GET method
        $updatedBrand = $this->brandsModel->findBrand($brandId);

        // Return 200 code with updated book data
        $response->getBody()->write(json_encode($updatedBrand));

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }
}