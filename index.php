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
// CORS Preflight Requests (OPTIONS)
require_once __DIR__ . '/src/handlers/JsonErrorHandler.php'; // Defines sendError()
require_once __DIR__ . '/src/middlewares/ContentNegotiationMiddleware.php';
require_once __DIR__ . '/src/middlewares/CorsMiddleware.php';
require_once __DIR__ . '/src/middlewares/RequestLoggingMiddleware.php';

// Let route handlers read JSON request bodies.
$app->addBodyParsingMiddleware();

// Let Slim match requests to routes (Which route will handle the request).
$app->addRoutingMiddleware();

// User-defined Content Negotiation Middleware
$app->add(runContentNegotiationMiddleware($app->getResponseFactory()));

// Browser clients can connect without being blocked
$app->add(corsPreflightMiddleware($app->getResponseFactory()));

// Global Exception/Error Handler Middleware using JsonErrorHandler
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(
    function (Request $request, Throwable $exception) use ($app) {
        $statusCode = 500;
        $message = 'Internal Server Error';

        if ($exception instanceof \Slim\Exception\HttpException) {
            $statusCode = $exception->getCode();
            $message = $exception->getMessage();
        }

        $response = sendError(
            $app->getResponseFactory(),
            $statusCode,
            $message
        );

        if ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException) {
            $response = $response->withHeader(
                'Allow',
                implode(', ', $exception->getAllowedMethods())
            );
        }

        return $response;
    }
);

// Log requests and include a request ID in every API response
$app->add(requestLoggingMiddleware());

// Calling Input Validations before routes
require_once __DIR__ . '/src/validations/BrandsInputValidator.php';
require_once __DIR__ . '/src/validations/PartsInputValidator.php';

// Calling models
require_once __DIR__ . '/src/models/BrandsModel.php';
require_once __DIR__ . '/src/models/PartsModel.php';
require_once __DIR__ . '/src/models/UsersModel.php';
require_once __DIR__ . '/src/models/OrdersModel.php';

// Calling external services
require_once __DIR__ . '/src/services/ExchangeRateService.php';

// Calling controllers
require_once __DIR__ . '/src/controllers/BrandsController.php';
require_once __DIR__ . '/src/controllers/PartsController.php';
require_once __DIR__ . '/src/controllers/UsersController.php';
require_once __DIR__ . '/src/controllers/OrdersController.php';

// Calling routes
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
