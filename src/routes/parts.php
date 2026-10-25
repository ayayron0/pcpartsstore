<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/parts', function (Request $request, Response $response) use ($pdo) {
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
            $response->getBody()->write(
                json_encode(['error' => 'Invalid query parameter'])
            );

            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
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
        $response->getBody()->write(
            json_encode(['error' => 'Invalid pagination'])
        );

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    // Brand ID validation
    if ($brand_id !== null && (!is_numeric($brand_id) || (int) $brand_id < 1)) {
        $response->getBody()->write(
            json_encode(['error' => 'Invalid brand ID'])
        );

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
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

$app->get('/parts/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $stmt = $pdo->prepare(
        "SELECT * FROM parts WHERE id = :id"
    );

    $stmt->execute([
        'id' => $args['id']
    ]);

    $part = $stmt->fetch();

    if (!$part) {

        $response->getBody()->write(
            json_encode([
                'error' => 'PC part not found'
            ])
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(
        json_encode($part)
    );

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json');
});

$app->post('/parts', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode(
        $request->getBody()->getContents(),
        true
    );

    if (
        !isset($data['name']) || !isset($data['category']) || !isset($data['brand_id']) || !isset($data['price'])
    ) {
        $response->getBody()->write(
            json_encode(['error' => 'Brand fields is required'])
        );

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $stmt = $pdo->prepare("INSERT INTO parts (name, category, brand_id, price) VALUES (:name, :category, :brand_id, :price)");

    $stmt->execute([
        'name' => $data['name'],
        'category' => $data['category'],
        'brand_id' => $data['brand_id'],
        'price' => $data['price']
    ]);

    $response->getBody()->write(
        json_encode([
            'message' => 'New PC part added',
            'id' => $pdo->lastInsertId()
        ])
    );

    return $response
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json');
});

$app->delete('/parts/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($pdo) {
    $stmt = $pdo->prepare(
        "DELETE FROM parts WHERE id = :id"
    );

    $stmt->execute([
        'id' => $args['id']
    ]);

    if ($stmt->rowCount() === 0) {
        $response->getBody()->write(
            json_encode(['error' => 'PC part not found'])
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
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
) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);

    if (!isset($data['price'])) {
        $response->getBody()->write(
            json_encode(['error' => 'Missing price field'])
        );

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $stmt = $pdo->prepare(
        "UPDATE parts
     SET price = :price
     WHERE id = :id"
    );

    $stmt->execute([
        'price' => $data['price'],
        'id' => $args['id']
    ]);

    if ($stmt->rowCount() === 0) {
        $response->getBody()->write(
            json_encode(['error' => 'PC part not found'])
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(
        json_encode(['message' => 'PC part\'s price updated successfully'])
    );

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json');
});
