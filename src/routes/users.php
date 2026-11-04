<?php

use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$usersModel = new UsersModel($pdo);
$usersController = new UsersController(
    $usersModel,
    $app->getResponseFactory()
);

$app->get('/users', [$usersController, 'index']);
$app->get('/users/{id}', [$usersController, 'show']);
$app->post('/users', [$usersController, 'store']);
$app->patch('/users/{id}', [$usersController, 'patch']);
$app->delete('/users/{id}', [$usersController, 'delete']);