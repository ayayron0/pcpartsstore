<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$app->get('/brands', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT * FROM brands");

    $brands = $stmt->fetchAll();

    $response->getBody()->write(
        json_encode($brands)
    );

    return $response
        ->withHeader('Content-Type', 'application/json');
});

$app->get('/brands/{id}', function (
    Request $request,
    Response $response,
    array $args
) use ($pdo) {
    $stmt = $pdo->prepare(
        "SELECT * FROM brands WHERE id = :id"
    );

    $stmt->execute([
        'id' => $args['id']
    ]);

    $brand = $stmt->fetch();

    if (!$brand) {

        $response->getBody()->write(
            json_encode([
                'error' => 'Brand not found'
            ])
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(
        json_encode($brand)
    );

    return $response
        ->withHeader('Content-Type', 'application/json');
});

$app->post('/brands', function (Request $request, Response $response) use ($pdo) {
    $data = json_decode(
        $request->getBody()->getContents(),
        true
    );

    if (!isset($data['name'])) {
        $response->getBody()->write(
            json_encode(['error' => 'Brand name is required'])
        );

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (:name)");

    $stmt->execute([
        'name' => $data['name']
    ]);

    $response->getBody()->write(
        json_encode([
            'message' => 'New brand added',
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
) use ($pdo) {
    $stmt = $pdo->prepare(
        "DELETE FROM brands WHERE id = :id"
    );

    $stmt->execute([
        'id' => $args['id']
    ]);

    if ($stmt->rowCount() === 0) {
        $response->getBody()->write(
            json_encode(['error' => 'Brand not found'])
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
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
) use ($pdo) {
    $data = json_decode($request->getBody()->getContents(), true);

    if (!isset($data['name'])) {
        $response->getBody()->write(
            json_encode(['error' => 'Missing name field'])
        );

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $stmt = $pdo->prepare(
        "UPDATE brands
     SET name = :name
     WHERE id = :id"
    );

    $stmt->execute([
        'name' => $data['name'],
        'id' => $args['id']
    ]);

    if ($stmt->rowCount() === 0) {
        $response->getBody()->write(
            json_encode(['error' => 'Brand not found'])
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(
        json_encode(['message' => 'Brand name updated successfully'])
    );

    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json');
});
