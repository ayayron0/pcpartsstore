<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

function validatePartsInput(?array $input): array
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

    // 3. Validate 'category'
    if (!isset($input['category']) || !is_string($input['category'])) {
        $errors['category'] = "Category is required and must be a string.";
    } else {
        $category = trim($input['category']);
        if (strlen($category) < 2 || strlen($category) > 255) {
            $errors['category'] = "Category must be between 2 and 255 characters.";
        }
    }

    // 4. Validate 'brand_id'
    if (!isset($input['brand_id']) || !is_int($input['brand_id']) || $input['brand_id'] < 1) {
        $errors['brand_id'] = "brand_id is required and must be a positive integer.";
    }

    // 5. Validate 'price'
    if (
        !isset($input['price']) || (!is_int($input['price']) && !is_float($input['price'])) ||
        $input['price'] < 0
    ) {
        $errors['price'] = "price is required and must be a number.";
    }

    return $errors;
}
