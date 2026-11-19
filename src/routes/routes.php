<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/** @var App $app */
/** @var PDO $pdo */

// Models and controllers
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

$authController = new AuthController(
    $usersModel,
    $app->getResponseFactory()
);

// Public brand reads
$app->get('/brands', [$brandsController, 'index']);
$app->get('/brands/{id}', [$brandsController, 'show']);

// Admin-only brand writes
$app->post('/brands', [$brandsController, 'store'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

$app->delete('/brands/{id}', [$brandsController, 'delete'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

$app->patch('/brands/{id}', [$brandsController, 'patch'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

$app->put('/brands/{id}', [$brandsController, 'replace'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

// Orders require login.
// Role and ownership checks are handled in OrdersController.
$app->group('/orders', function (
    RouteCollectorProxy $group
) use ($ordersController) {
    $group->get('', [$ordersController, 'index']);
    $group->get('/{id}', [$ordersController, 'show']);
    $group->post('', [$ordersController, 'store']);
    $group->patch('/{id}', [$ordersController, 'patch']);
    $group->delete('/{id}', [$ordersController, 'delete']);
})->add(csrfMiddleware($app->getResponseFactory()))
  ->add(authenticationMiddleware($app->getResponseFactory()));

// Public part reads
$app->get('/parts', [$partsController, 'index']);
$app->get('/parts/{id}', [$partsController, 'show']);
$app->get(
    '/parts/{id}/converted-price',
    [$partsController, 'convertedPrice']
);

// Admin-only part writes
$app->post('/parts', [$partsController, 'store'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

$app->delete('/parts/{id}', [$partsController, 'delete'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

$app->patch('/parts/{id}', [$partsController, 'patch'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

$app->put('/parts/{id}', [$partsController, 'replace'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(adminMiddleware($app->getResponseFactory()));

// User routes require login.
// Role and ownership checks are handled in UsersController.
$app->group('/users', function (
    RouteCollectorProxy $group
) use ($usersController) {
    $group->get('', [$usersController, 'index']);
    $group->get('/{id}', [$usersController, 'show']);
    $group->patch('/{id}', [$usersController, 'patch']);
    $group->delete('/{id}', [$usersController, 'delete']);
})->add(csrfMiddleware($app->getResponseFactory()))
  ->add(authenticationMiddleware($app->getResponseFactory()));


// Public registration
$app->post('/users', [$usersController, 'store']);

// Authentication routes
$app->post('/auth/login', [$authController, 'login']);
$app->post('/auth/logout', [$authController, 'logout'])
    ->add(csrfMiddleware($app->getResponseFactory()))
    ->add(authenticationMiddleware($app->getResponseFactory()));
$app->get('/auth/me', [$authController, 'me'])
    ->add(authenticationMiddleware($app->getResponseFactory()));