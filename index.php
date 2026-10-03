<?php
require __DIR__ . '/backend/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

$app->setBasePath('/pcpartsstore');

$app->get('/', function (Request $request, Response $response){
    $response->getBody()->write(
        json_encode([
            'message' => 'PC Parts Store API is running'
        ])
    );
    
    return $response->withHeader(
        'Content-Type',
        'application/json'
    );
});

$app->run();