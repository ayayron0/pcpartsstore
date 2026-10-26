<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

// Helper function to send standardized JSON error responses
function sendError(ResponseFactory $responseFactory, int $statusCode, string $message, array $details = []): Response
{
    $data = [
        "status" => $statusCode,
        "error" => $message
    ];

    if (!empty($details)) {
        $data["details"] = $details;
    }

    $response = $responseFactory->createResponse($statusCode);
    $response->getBody()->write(
        json_encode($data)
    );

    return $response->withHeader('Content-Type', 'application/json');
}
