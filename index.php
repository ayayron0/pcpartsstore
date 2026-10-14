<?php
require __DIR__ . '/backend/vendor/autoload.php';
$pdo = require __DIR__ . '/src/config/dbconnection.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

$app->setBasePath('/pcpartsstore');

require __DIR__ . '/src/routes/brands.php';
require __DIR__ . '/src/routes/parts.php';
require __DIR__ . '/src/routes/users.php';
require __DIR__ . '/src/routes/orders.php';

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(
        json_encode([
            'message' => 'PC Parts Store API is running'
        ])
    );

    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
