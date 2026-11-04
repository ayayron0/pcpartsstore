<?php

use Slim\App;

/** @var App $app */
/** @var PDO $pdo */

$partsModel = new PartsModel($pdo);
$partsController = new PartsController(
    $partsModel,
    $app->getResponseFactory()
);

$app->get('/parts', [$partsController, 'index']);
$app->get('/parts/{id}', [$partsController, 'show']);
$app->post('/parts', [$partsController, 'store']);
$app->delete('/parts/{id}', [$partsController, 'delete']);
$app->patch('/parts/{id}', [$partsController, 'patch']);
$app->put('/parts/{id}', [$partsController, 'replace']);
