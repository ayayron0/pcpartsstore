<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

function csrfMiddleware(ResponseFactory $responseFactory)
{
    return function (
        Request $request,
        RequestHandler $requestHandler
    ) use ($responseFactory): Response {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $method = strtoupper($request->getMethod());

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $requestHandler->handle($request);
        }

        if (!isset($_SESSION['user_id'])) {
            return sendError(
                $responseFactory,
                401,
                'Authentication Required'
            );
        }

        $expectedToken = $_SESSION['csrf_token'] ?? null;
        $providedToken = $request->getHeaderLine('X-CSRF-Token');

        if (
            !is_string($expectedToken) ||
            $expectedToken === '' ||
            $providedToken === '' ||
            !hash_equals($expectedToken, $providedToken)
        ) {
            return sendError(
                $responseFactory,
                403,
                'Invalid or missing CSRF token.'
            );
        }

        return $requestHandler->handle($request);
    };
}