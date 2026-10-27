<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/users', function (Request $request, Response $response) use ($app, $pdo) {
    //Read pagination and filter paramters
    $page = ($_GET['page'] ?? 1); 
    $limit = ($_GET['limit'] ?? 5);
    $name = $_GET['name'] ?? null;

    //reject unsupported query params
    $allowedParams = ['page', 'limit', 'name'];

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

    //validate name filter
    if ($name !== null && !is_string($name)) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Invalid name filter'
        );
    }

    //optional first-name or last-name filter
    $whereSql = '';
    $queryParams = [];

    if ($name !== null && trim($name) !== '') {
        $whereSql = 'WHERE first_name LIKE :first_name OR last_name LIKE :last_name';
        $queryParams['first_name'] = '%' . trim($name) . '%';
        $queryParams['last_name'] = '%' . trim($name) . '%';
    }

    //count matching records before pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
    $countStmt->execute($queryParams);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $limit);

    //fetch one page in a consistent ID order
    $stmt = $pdo->prepare(
        "SELECT id, first_name, last_name, email
        FROM users $whereSql
        ORDER BY id ASC
        LIMIT :limit OFFSET :offset"
    );

    //Bind filter values and pagination integers safely
    foreach ($queryParams as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
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

$app->get('/users/{id}', function (Request $request, Response $response, array $args) use ($app, $pdo) {
    // Require a positive integer user ID
    $userId = filter_var($args['id'], FILTER_VALIDATE_INT);

    if ($userId === false || $userId < 1) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Invalid user ID'
        );
    }

    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        return sendError(
            $app->getResponseFactory(),
            404,
            'User not found'
        );
    }
    $response->getBody()->write(json_encode($user));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/users', function (Request $request, Response $response) use ($app, $pdo) {
    // Read the JSON body parsed by Slim middleware
    $data = $request->getParsedBody();

    // Reject a missing or malformed JSON body
    if (!is_array($data)) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Malformed JSON payload.'
        );
    }

    // Check required user fields
    if (!isset($data['first_name'], $data['last_name'], $data['email'])) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            ['fields' => 'First name, last name and email are required.']
        );
    }

    foreach (['first_name' => 100, 'last_name' => 100, 'email' => 255] as $field => $maxLength) {
        if (!is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen(trim($data[$field])) > $maxLength) {
            return sendError(
                $app->getResponseFactory(),
                422,
                'Validation Failed',
                [$field => "Must be a non-empty string of at most $maxLength characters."]
            );
        }
        
        $data[$field] = trim($data[$field]);
    }

    // Validate the email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            ['email' => 'A valid email address is required.']
        );
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email) VALUES (:first_name, :last_name, :email)');
        $stmt->execute(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email']]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
            throw $e;
        }
        return sendError(
            $app->getResponseFactory(),
            409,
            'Email already exists'
        );
    }

    $response->getBody()->write(json_encode(['message' => 'New user added', 'id' => $pdo->lastInsertId()]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

$app->patch('/users/{id}', function (Request $request, Response $response, array $args) use ($app, $pdo) {
    // Require a positive integer user ID
    $userId = filter_var($args['id'], FILTER_VALIDATE_INT);

    if ($userId === false || $userId < 1) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Invalid user ID'
        );
    }

    // Read the JSON body parsed by Slim middleware
    $data = $request->getParsedBody();

    // Reject a missing or malformed JSON body
    if (!is_array($data)) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Malformed JSON payload.'
        );
    }

    // Require at least one supported user field
    if (
        !$data ||
        array_diff(array_keys($data), ['first_name', 'last_name', 'email'])
    ) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            ['fields' => 'Provide first_name, last_name or email only.']
        );
    }

    $updates = [];
    $params = ['id' => $args['id']];

    foreach (['first_name' => 100, 'last_name' => 100, 'email' => 255] as $field => $maxLength) {
        if (!array_key_exists($field, $data)) {
            continue;
        }
        if (!is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen(trim($data[$field])) > $maxLength) {
            return sendError(
                $app->getResponseFactory(),
                422,
                'Validation Failed',
                [$field => "Must be a non-empty string of at most $maxLength characters."]
            );
        }

        $params[$field] = trim($data[$field]);
        $updates[] = "$field = :$field";
    }

    // Validate the email format when supplied
    if (isset($params['email']) && !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
        return sendError(
            $app->getResponseFactory(),
            422,
            'Validation Failed',
            ['email' => 'A valid email address is required.']
        );
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);

    // Return 404 if the user does not exist
    if (!$stmt->fetch()) {
        return sendError(
            $app->getResponseFactory(),
            404,
            'User not found'
        );
    }
    try {
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id');
        $stmt->execute($params);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
            throw $e;
        }
        return sendError(
            $app->getResponseFactory(),
            409,
            'Email already exists'
        );
    }

    $response->getBody()->write(json_encode(['message' => 'User updated successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/users/{id}', function (Request $request, Response $response, array $args) use ($app, $pdo) {
    // Require a positive integer user ID
    $userId = filter_var($args['id'], FILTER_VALIDATE_INT);

    if ($userId === false || $userId < 1) {
        return sendError(
            $app->getResponseFactory(),
            400,
            'Invalid user ID'
        );
    }
    
    try {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $args['id']]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1451) {
            throw $e;
        }
        return sendError(
            $app->getResponseFactory(),
            409,
            'Cannot remove a user with orders'
        );
    }
    // Return 404 if no user was deleted
    if ($stmt->rowCount() === 0) {
        return sendError(
            $app->getResponseFactory(),
            404,
            'User not found'
        );
    }

    $response->getBody()->write(json_encode(['message' => 'User removed successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});
