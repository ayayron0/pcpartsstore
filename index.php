<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/backend/vendor/autoload.php';
$pdo = require __DIR__ . '/src/config/dbconnection.php';

$app = AppFactory::create();

$app->setBasePath('/pcpartsstore');

// Request Parsing Middleware, Routing Middleware and Content Negotiation Middleware 
// Global Exception Handler Middleware and JSON Helper Handler
require_once __DIR__ . '/src/Helpers/JsonErrorHandler.php'; // Defines sendError()
require_once __DIR__ . '/src/Middleware/ContentNegotiationMiddleware.php';

// Let route handlers read JSON request bodies.
$app->addBodyParsingMiddleware();

// Let Slim match requests to routes (Which route will handle the request).
$app->addRoutingMiddleware();

// User-defined Content Negotiation Middleware
$app->add(runContentNegotiationMiddleware($app->getResponseFactory()));

// Global Exception/Error Handler Middleware using JsonErrorHandler
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(
    function (Request $request, Throwable $exception) use ($app) {
        // In production , you would log $exception ->getMessage() to a file
        // rather than exposing internal database details to the user.   
        return sendError(
            $app->getResponseFactory(),
            500,
            'Internal Server Error',
        );
    }
);

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
