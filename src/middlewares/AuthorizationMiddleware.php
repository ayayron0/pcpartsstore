<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

function adminMiddleware(ResponseFactory $responseFactory)
{
    return function (
        Request $request,
        RequestHandler $requestHandler
    ) use ($responseFactory): Response {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return sendError(
                $responseFactory,
                401,
                'Authentication Required'
            );
        }

        if (($_SESSION['role'] ?? '') !== 'admin') {
            return sendError(
                $responseFactory,
                403,
                'Admin permission required.'
            );
        }

        return $requestHandler->handle($request);
    };
}