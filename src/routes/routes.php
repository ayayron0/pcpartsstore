<?php

use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

// Global Route for all endpoints
$brandsModel = new BrandsModel($pdo);
$brandsController = new BrandsController(
    $brandsModel,
    $app->getResponseFactory()
);

$ordersModel = new OrdersModel($pdo);
$ordersController = new OrdersController(
    $ordersModel,
    $app->getResponseFactory()
);

$partsModel = new PartsModel($pdo);
$partsController = new PartsController(
    $partsModel,
    $app->getResponseFactory()
);

$usersModel = new UsersModel($pdo);
$usersController = new UsersController(
    $usersModel,
    $app->getResponseFactory()
);

// Brand routes
$app->get('/brands', [$brandsController, 'index']);
$app->get('/brands/{id}', [$brandsController, 'show']);
$app->post('/brands', [$brandsController, 'store']);
$app->delete('/brands/{id}', [$brandsController, 'delete']);
$app->patch('/brands/{id}', [$brandsController, 'patch']);
$app->put('/brands/{id}', [$brandsController, 'replace']);

// Order routes
$app->get('/orders', [$ordersController, 'index']);
$app->get('/orders/{id}', [$ordersController, 'show']);
$app->post('/orders', [$ordersController, 'store']);
$app->patch('/orders/{id}', [$ordersController, 'patch']);
$app->delete('/orders/{id}', [$ordersController, 'delete']);

// Part routes
$app->get('/parts', [$partsController, 'index']);
$app->get('/parts/{id}', [$partsController, 'show']);
$app->get('/parts/{id}/converted-price', [$partsController, 'convertedPrice']);
$app->post('/parts', [$partsController, 'store']);
$app->delete('/parts/{id}', [$partsController, 'delete']);
$app->patch('/parts/{id}', [$partsController, 'patch']);
$app->put('/parts/{id}', [$partsController, 'replace']);

// User routes
$app->get('/users', [$usersController, 'index']);
$app->get('/users/{id}', [$usersController, 'show']);
$app->post('/users', [$usersController, 'store']);
$app->patch('/users/{id}', [$usersController, 'patch']);
$app->delete('/users/{id}', [$usersController, 'delete']);
