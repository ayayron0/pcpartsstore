<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

function authenticationMiddleware(ResponseFactory $responseFactory)
{
    return function (Request $request, RequestHandler $requestHandler) use ($responseFactory): Response {
        // Start the session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if login stored a user ID
        if (!isset($_SESSION['user_id'])) {
            return sendError($responseFactory, 401, 'Authentication Required');
        }

        return $requestHandler->handle($request);
    };
}
