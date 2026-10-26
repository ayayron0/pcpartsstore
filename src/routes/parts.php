<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/parts', function (Request $request, Response $response) use ($app, $pdo) {
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
    $category = $_GET['category'] ?? null;
    $brand_id = $_GET['brand_id'] ?? null;

    // Invalid query parameters validation
    $allowedParams = ['page', 'limit', 'category', 'brand_id'];

    foreach ($_GET as $key => $value) {
        if (!in_array($key, $allowedParams)) {
            // $response->getBody()->write(
            //     json_encode(['error' => 'Invalid query parameter'])
            // );

            // return $response
            //     ->withStatus(400)
            //     ->withHeader('Content-Type', 'application/json');

            return sendError(
                $app->getResponseFactory(),
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
            $app->getResponseFactory(),
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
            $app->getResponseFactory(),
            400,
            'Invalid brand ID'
        );
    }

    // 2. Build Dynamic Filtering (WHERE clauses)
    $whereClauses = [];
    $queryParams = [];

    if (isset($category)) {
        $whereClauses[] = "category = :category";
        $queryParams['category'] = $category;
    }

    if (isset($brand_id)) {
        $whereClauses[] = "brand_id = :brand_id";
        $queryParams['brand_id'] = $brand_id;
    }

    // Combine WHERE clauses if any exist
    $whereSql = "";
    if (count($whereClauses) > 0) {
        $whereSql = "WHERE " . implode(" AND ", $whereClauses);
    }
    // $whereSql = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // 3. Get Total Count (for metadata)s
    $countSql = "SELECT COUNT(*) AS total FROM parts $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($queryParams);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // 4. Fetch the Paginated Data
    $dataSql = "SELECT * FROM parts $whereSql ORDER BY id ASC LIMIT :limit OFFSET :offset";
    $dataStmt = $pdo->prepare($dataSql);

    // Bind filter and pagination parameters
    $dataStmt->execute(array_merge($queryParams, [
        'limit' => $limit,
        'offset' => $offset,
    ]));
    $parts = $dataStmt->fetchAll();

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
});

$app->get('/parts/{id}', function (Request $request, Response $response, array $args) use ($app, $pdo) {
    $partId = $args['id'];

    $stmt = $pdo->prepare(
        "SELECT * FROM parts WHERE id = :id"
    );

    $stmt->execute([
        'id' => $partId
    ]);

    $part = $stmt->fetch();

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
            $app->getResponseFactory(),
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
});

$app->post('/parts', function (Request $request, Response $response) use ($app, $pdo) {
    // $input = json_decode(
    //     $request->getBody()->getContents(),
    //     true
    // );

    $input = $request->getParsedBody();

    // Malformed JSON or no JSON object
    if (!is_array($input)) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Malformed JSON payload.'
        );
    }

    // Valid JSON, but fields failed validation
    $validationErrors = validatePartsInput($input);

    if (!empty($validationErrors)) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            $validationErrors
        );
    }

    // Save trimmed text values
    $stmt = $pdo->prepare(
        'INSERT INTO parts (name, category, brand_id, price)
         VALUES (:name, :category, :brand_id, :price)'
    );

    $stmt->execute([
        'name' => trim($input['name']),
        'category' => trim($input['category']),
        'brand_id' => $input['brand_id'],
        'price' => $input['price'],
    ]);

    $response->getBody()->write(json_encode([
        'message' => 'New PC part added',
        'id' => $pdo->lastInsertId(),
    ]));

    return $response
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json');
});

$app->delete('/parts/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($app, $pdo) {
    $partId = $args['id'];

    $stmt = $pdo->prepare(
        "DELETE FROM parts WHERE id = :id"
    );

    $stmt->execute([
        'id' => $partId
    ]);

    if ($stmt->rowCount() === 0) {
        // $response->getBody()->write(
        //     json_encode(['error' => 'PC part not found'])
        // );

        // return $response
        //     ->withStatus(404)
        //     ->withHeader('Content-Type', 'application/json');

        return sendError(
            $app->getResponseFactory(),
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
});

$app->patch('/parts/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($app, $pdo) {
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
            $app->getResponseFactory(),
            400,
            'Malformed JSON payload.'
        );
    }

    if (!isset($input['price'])) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            ['price' => 'Price is required.']
        );
    }

    $stmt = $pdo->prepare(
        "UPDATE parts
     SET price = :price
     WHERE id = :id"
    );

    $stmt->execute([
        'price' => $input['price'],
        'id' => $args['id']
    ]);

    if ($stmt->rowCount() === 0) {
        // $response->getBody()->write(
        //     json_encode(['error' => 'PC part not found'])
        // );

        // return $response
        //     ->withStatus(404)
        //     ->withHeader('Content-Type', 'application/json');

        return sendError($app->getResponseFactory(), 404, 'PC part not found');
    }

    $response->getBody()->write(
        json_encode(['message' => 'PC part\'s price updated successfully'])
    );

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json');
});

$app->put('/parts/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($app, $pdo) {
    $input = $request->getParsedBody();
    $partId = $args['id'];

    // Check if JSON decoding failed completely
    if (!is_array($input)) {
        return sendError($app->getResponseFactory(), 400, "Malformed JSON payload.");
    }

    // Run validation
    $validationErrors = validatePartsInput($input);

    if (!empty($validationErrors)) {
        return sendError($app->getResponseFactory(), 422, "Validation Failed", $validationErrors);
    }

    // Check if the PC_part id exists
    $stmt = $pdo->prepare('SELECT id FROM parts WHERE id = :id');
    $stmt->execute(['id' => $partId]);
    $part = $stmt->fetch();

    if (empty($part)) {
        return sendError($app->getResponseFactory(), 404, "PC part not found");
    }

    // Update parts based on inputs
    $putStmt = $pdo->prepare("UPDATE parts SET name = :name, category = :category, brand_id = :brand_id, price = :price WHERE id = :id");

    $putStmt->execute([
        'name' => trim($input['name']),
        'category' => trim($input['category']),
        'brand_id' => $input['brand_id'],
        'price' => $input['price'],
        'id' => $partId
    ]);

    // Fetch updated part data using GET method
    $getStmt = $pdo->prepare('SELECT * FROM parts WHERE id = :id');
    $getStmt->execute(['id' => $partId]);
    $updatedPart = $getStmt->fetch();

    // Return 200 code with updated book data
    $response->getBody()->write(json_encode($updatedPart));

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json');
});
