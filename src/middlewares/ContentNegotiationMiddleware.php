<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

function runContentNegotiationMiddleware(ResponseFactory $responseFactory)
{

    return function (Request $request, RequestHandler $requestHandler) use ($responseFactory): Response {

        // 1. Check the 'Accept' header (What the client wants back)
        $acceptHeader = $request->getHeaderLine('Accept') ?: '*/*';

        // If client doesn't accept JSON or wildcard (*/*), reject with 406
        if (strpos($acceptHeader, 'application/json') === false && strpos($acceptHeader, '*/*') === false) {
            return sendError($responseFactory, 406, "Not Acceptable. This API only provides application/json.");
        }

        $method = strtoupper($request->getMethod());

        // 2. Check 'Content -Type' for methods that send a body
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->getHeaderLine('Content-Type');

            // If they aren't sending JSON , reject with 415
            if (strpos($contentType, 'application/json') === false) {
                return sendError($responseFactory, 415, "Unsupported Media Type. Please send application/json.");
            }
        }

        return $requestHandler->handle($request);
    };
}
