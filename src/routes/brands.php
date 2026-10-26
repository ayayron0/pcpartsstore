<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/brands', function (Request $request, Response $response) use ($app, $pdo) {
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

    // $page = (int)$page;
    // $limit = 5;

    if (!is_numeric($page) || $page < 1 || $page > 100) {
        return sendError(
            $app->getResponseFactory(),
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
    $countSql = "SELECT COUNT(*) AS total FROM brands $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($queryParams);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // 4. Fetch the Paginated Data
    $dataSql = "SELECT * FROM brands $whereSql ORDER BY id ASC LIMIT :limit OFFSET :offset";
    $dataStmt = $pdo->prepare($dataSql);

    // Bind filter and pagination parameters
    $dataStmt->execute(array_merge($queryParams, [
        'limit' => $limit,
        'offset' => $offset
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

$app->get('/brands/{id}', function (Request $request, Response $response, array $args) use ($app, $pdo) {
    $brandId = $args['id'];

    $stmt = $pdo->prepare(
        "SELECT * FROM brands WHERE id = :id"
    );

    $stmt->execute([
        'id' => $brandId
    ]);

    $brand = $stmt->fetch();

    if (!$brand) {
        return sendError(
            $app->getResponseFactory(),
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
});

$app->post('/brands', function (Request $request, Response $response) use ($app, $pdo) {
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
    $validationErrors = validateBrandsInput($input);

    if (!empty($validationErrors)) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            $validationErrors
        );
    }

    // Save trimmed text values
    $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (:name)");

    $stmt->execute([
        'name' => trim($input['name'])
    ]);

    $response->getBody()->write(
        json_encode([
            'message' => 'New PC brand added',
            'id' => $pdo->lastInsertId()
        ])
    );

    return $response
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json');
});

$app->delete('/brands/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($app, $pdo) {
    $brandId = $args['id'];

    $stmt = $pdo->prepare(
        "DELETE FROM brands WHERE id = :id"
    );

    $stmt->execute([
        'id' => $brandId
    ]);

    if ($stmt->rowCount() === 0) {
        return sendError(
            $app->getResponseFactory(),
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
});

$app->patch('/brands/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($app, $pdo) {
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
            $app->getResponseFactory(),
            400,
            'Malformed JSON payload.'
        );
    }

    if (!isset($input['name'])) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            ['name' => 'Name is required.']
        );
    }

    $stmt = $pdo->prepare(
        "UPDATE brands
     SET name = :name
     WHERE id = :id"
    );

    $stmt->execute([
        'name' => $input['name'],
        'id' => $brandId
    ]);

    if ($stmt->rowCount() === 0) {
        return sendError($app->getResponseFactory(), 404, 'PC brand not found');
    }

    $response->getBody()->write(
        json_encode(['message' => 'Brand name updated successfully'])
    );

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json');
});

$app->put('/brands/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($app, $pdo) {
    $input = $request->getParsedBody();
    $brandId = $args['id'];

    // Check if JSON decoding failed completely
    if (!is_array($input)) {
        return sendError($app->getResponseFactory(), 400, "Malformed JSON payload.");
    }

    // Run validation
    $validationErrors = validateBrandsInput($input);

    if (!empty($validationErrors)) {
        return sendError($app->getResponseFactory(), 422, "Validation Failed", $validationErrors);
    }

    // Check if the PC_part id exists
    $stmt = $pdo->prepare('SELECT id FROM brands WHERE id = :id');
    $stmt->execute(['id' => $brandId]);
    $part = $stmt->fetch();

    if (empty($part)) {
        return sendError($app->getResponseFactory(), 404, "PC brand not found");
    }

    // Update parts based on inputs
    $putStmt = $pdo->prepare("UPDATE brands SET name = :name WHERE id = :id");

    $putStmt->execute([
        'name' => trim($input['name']),
        'id' => $brandId
    ]);

    // Fetch updated part data using GET method
    $getStmt = $pdo->prepare('SELECT * FROM brands WHERE id = :id');
    $getStmt->execute(['id' => $brandId]);
    $updatedBrand = $getStmt->fetch();

    // Return 200 code with updated book data
    $response->getBody()->write(json_encode($updatedBrand));

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json');
});
