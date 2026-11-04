<?php

use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$brandsModel = new BrandsModel($pdo);
$brandsController = new BrandsController(
    $brandsModel,
    $app->getResponseFactory()
);

$app->get('/brands', [$brandsController, 'index']);
$app->get('/brands/{id}', [$brandsController, 'show']);
$app->post('/brands', [$brandsController, 'store']);
$app->delete('/brands/{id}', [$brandsController, 'delete']);
$app->patch('/brands/{id}', [$brandsController, 'patch']);
$app->put('/brands/{id}', [$brandsController, 'replace']);