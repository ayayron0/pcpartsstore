<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

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

$authModel = new UsersModel($pdo);
$authController = new AuthController(
    $authModel,
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
$app->group('/orders', function (RouteCollectorProxy $group) use ($ordersController) {
    $group->get('', [$ordersController, 'index']);
    $group->get('/{id}', [$ordersController, 'show']);
    $group->post('', [$ordersController, 'store']);
    $group->patch('/{id}', [$ordersController, 'patch']);
    $group->delete('/{id}', [$ordersController, 'delete']);
})->add(authenticationMiddleware($app->getResponseFactory()));

// Part routes
$app->get('/parts', [$partsController, 'index']);
$app->get('/parts/{id}', [$partsController, 'show']);
$app->get('/parts/{id}/converted-price', [$partsController, 'convertedPrice']);
$app->post('/parts', [$partsController, 'store']);
$app->delete('/parts/{id}', [$partsController, 'delete']);
$app->patch('/parts/{id}', [$partsController, 'patch']);
$app->put('/parts/{id}', [$partsController, 'replace']);

// User routes that require a signed-in user
$app->group('/users', function (RouteCollectorProxy $group) use ($usersController) {
    $group->get('', [$usersController, 'index']);
    $group->get('/{id}', [$usersController, 'show']);
    $group->patch('/{id}', [$usersController, 'patch']);
    $group->delete('/{id}', [$usersController, 'delete']);
})->add(authenticationMiddleware($app->getResponseFactory()));

// Public registration route
$app->post('/users', [$usersController, 'store']);

// Auth routes
$app->post('/auth/login', [$authController, 'login']);
