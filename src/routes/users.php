<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/users', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query('SELECT id, first_name, last_name, email FROM users ORDER BY id ASC');
    $response->getBody()->write(json_encode($stmt->fetchAll()));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/users/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $user = $stmt->fetch();
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'User not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode($user));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/users', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    if (!is_array($data) || !isset($data['first_name'], $data['last_name'], $data['email'])) {
        $response->getBody()->write(json_encode(['error' => 'First name, last name and email are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    foreach (['first_name' => 100, 'last_name' => 100, 'email' => 255] as $field => $maxLength) {
        if (!is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen(trim($data[$field])) > $maxLength) {
            $response->getBody()->write(json_encode(['error' => 'Invalid user fields']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $data[$field] = trim($data[$field]);
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid email']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email) VALUES (:first_name, :last_name, :email)');
        $stmt->execute(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email']]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
            throw $e;
        }
        $response->getBody()->write(json_encode(['error' => 'Email already exists']));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode(['message' => 'New user added', 'id' => $pdo->lastInsertId()]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

$app->patch('/users/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);
    if (!is_array($data) || !$data || array_diff(array_keys($data), ['first_name', 'last_name', 'email'])) {
        $response->getBody()->write(json_encode(['error' => 'Provide first_name, last_name or email only']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    $updates = [];
    $params = ['id' => $args['id']];
    foreach (['first_name' => 100, 'last_name' => 100, 'email' => 255] as $field => $maxLength) {
        if (!array_key_exists($field, $data)) {
            continue;
        }
        if (!is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen(trim($data[$field])) > $maxLength) {
            $response->getBody()->write(json_encode(['error' => 'Invalid user fields']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $params[$field] = trim($data[$field]);
        $updates[] = "$field = :$field";
    }
    if (isset($params['email']) && !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid email']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'User not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    try {
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id');
        $stmt->execute($params);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
            throw $e;
        }
        $response->getBody()->write(json_encode(['error' => 'Email already exists']));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode(['message' => 'User updated successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/users/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    try {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $args['id']]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1451) {
            throw $e;
        }
        $response->getBody()->write(json_encode(['error' => 'Cannot remove a user with orders']));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    }
    if ($stmt->rowCount() === 0) {
        $response->getBody()->write(json_encode(['error' => 'User not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode(['message' => 'User removed successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});
