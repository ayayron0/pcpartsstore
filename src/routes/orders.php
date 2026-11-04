<?php

use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$ordersModel = new OrdersModel($pdo);
$ordersController = new OrdersController(
    $ordersModel,
    $app->getResponseFactory()
);

$app->get('/orders', [$ordersController, 'index']);
$app->get('/orders/{id}', [$ordersController, 'show']);
$app->post('/orders', [$ordersController, 'store']);
$app->patch('/orders/{id}', [$ordersController, 'patch']);
$app->delete('/orders/{id}', [$ordersController, 'delete']);