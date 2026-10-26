<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/orders', function (Request $request, Response $response) use ($app, $pdo) {
    //Read pagination and filter paramters
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 5;
    $user_id = $_GET['user_id'] ?? null;
    $status = $_GET['status'] ?? null;

    //reject unsupported query params
    $allowedParams = ['page', 'limit', 'user_id', 'status']; 

    foreach ($_GET as $key => $value) {
        if (!in_array($key, $allowedParams)) {
            return sendError(
                $app->getResponseFactory(),
                400,
                'Invalid query parameter'
            );
        }
    }

    //validate page number and page size
    if (
        filter_var($page, FILTER_VALIDATE_INT) === false ||
        $page < 1 ||
        $page > 100 ||
        filter_var($limit, FILTER_VALIDATE_INT) === false ||
        $limit < 1 ||
        $limit > 50) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Invalid pagination'
        );
    }

    $offset = ($page - 1) * $limit; //how many rows to skip

    //validate user ID filter
    if ($user_id !== null && (filter_var($user_id, FILTER_VALIDATE_INT) === false || $user_id < 1)) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Invalid user ID'
        );
    }

    //validate order status filter
    $allowedStatuses = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];

    if ($status !== null && !in_array($status, $allowedStatuses, true)) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Invalid order status'
        );
    }

    //build optional filters, the supplied filters must both match
    $whereClauses = [];
    $queryParams = [];

    if ($user_id !== null) {
        $whereClauses[] = 'user_id = :user_id';
        $queryParams['user_id'] = (int) $user_id;
    }

    if ($status !== null) {
        $whereClauses[] = 'status = :status';
        $queryParams['status'] = $status;
    }

    $whereSql = '';

    if (count($whereClauses) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    //count matching records before pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders $whereSql");
    $countStmt->execute($queryParams);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $limit);

    //fetch one page in a consistent ID order
    $stmt = $pdo->prepare(
        "SELECT id, user_id, status
         FROM orders $whereSql
         ORDER BY id ASC
         LIMIT :limit OFFSET :offset"
    );

    //Bind filter values and pagination integers safely
    foreach ($queryParams as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue(':' . $key, $value, $type);
    }

    $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
    $stmt->execute();

    $response->getBody()->write(
        json_encode([
            'metadata' => [
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'current_page' => (int) $page,
                'per_page' => (int) $limit
            ],
            'data' => $stmt->fetchAll()
        ])
    );

    return $response->withHeader('Content-Type', 'application/json');

});

$app->get('/orders/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $stmt = $pdo->prepare('SELECT id, user_id, status FROM orders WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $order = $stmt->fetch();
    if (!$order) {
        $response->getBody()->write(json_encode(['error' => 'Order not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode($order));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/orders', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    if (!is_array($data) || !isset($data['user_id']) || !is_int($data['user_id']) || $data['user_id'] < 1) {
        $response->getBody()->write(json_encode(['error' => 'A positive integer user_id is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    $status = array_key_exists('status', $data) ? $data['status'] : 'Processing';
    if (!in_array($status, ['Processing', 'Shipped', 'Delivered', 'Cancelled'], true)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid order status']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO orders (user_id, status) VALUES (:user_id, :status)');
        $stmt->execute(['user_id' => $data['user_id'], 'status' => $status]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1452) {
            throw $e;
        }
        $response->getBody()->write(json_encode(['error' => 'User not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode(['message' => 'New order added', 'id' => $pdo->lastInsertId()]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

$app->patch('/orders/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    if (!is_array($data) || !isset($data['status']) || array_diff(array_keys($data), ['status']) ||
        !in_array($data['status'], ['Processing', 'Shipped', 'Delivered', 'Cancelled'], true)) {
        $response->getBody()->write(json_encode(['error' => 'A valid status field is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Order not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
    $stmt->execute(['status' => $data['status'], 'id' => $args['id']]);
    $response->getBody()->write(json_encode(['message' => 'Order status updated successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/orders/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    try {
        $stmt = $pdo->prepare('DELETE FROM orders WHERE id = :id');
        $stmt->execute(['id' => $args['id']]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1451) {
            throw $e;
        }
        $response->getBody()->write(json_encode(['error' => 'Cannot remove an order with items; cancel it instead']));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    }
    if ($stmt->rowCount() === 0) {
        $response->getBody()->write(json_encode(['error' => 'Order not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode(['message' => 'Order removed successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});
