<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

function requestLoggingMiddleware()
{
    return function (Request $request, RequestHandler $handler): Response {
        // Give each request a unique ID
        $requestId = bin2hex(random_bytes(8));
        $request = $request->withAttribute('request_id', $requestId);

        $startTime = microtime(true);

        // Process the request
        $response = $handler->handle($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Log request information without bodies or credentials
        error_log(json_encode([
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration
        ]));

        return $response->withHeader('X-Request-ID', $requestId);
    };
}