<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

function corsPreflightMiddleware(ResponseFactory $responseFactory)
{
    return function (Request $request, RequestHandler $requestHandler) use ($responseFactory): Response {
        if ($request->getMethod() === 'OPTIONS') 
            $response = $responseFactory->createResponse(200);
        else 
            $response = $requestHandler->handle($request);
        

        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, PATCH, DELETE, OPTIONS'
            )
            ->withHeader(
                'Access-Control-Allow-Headers',
                'Content-Type, Accept, Authorization'
            );
    };
}
