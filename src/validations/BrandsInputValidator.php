<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

function validateBrandsInput(?array $input): array
{
    $errors = [];
    // // 1. Check if JSON decoding failed completely
    // if ($input === null) {
    //     sendError(400, "Malformed JSON payload.");
    // }

    // 2. Validate 'name'
    if (!isset($input['name']) || !is_string($input['name'])) {
        $errors['name'] = "Name is required and must be a string.";
    } else {
        $name = trim($input['name']);
        if (strlen($name) < 2 || strlen($name) > 255) {
            $errors['name'] = "Name must be between 2 and 255 characters.";
        }
    }

    return $errors;
}
