<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/parts', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT * FROM parts");

    $parts = $stmt->fetchAll();

    $response->getBody()->write(
        json_encode($parts)
    );

    return $response
        ->withHeader('Content-Type', 'application/json');
});

$app->get('/parts/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($pdo) {
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
