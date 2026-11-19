<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private UsersModel $usersModel;
    private ResponseFactory $responseFactory;

    public function __construct(UsersModel $usersModel, ResponseFactory $responseFactory)
    {
        $this->usersModel = $usersModel;
        $this->responseFactory = $responseFactory;
    }

    public function login(Request $request, Response $response): Response
    {
        // Read the request body
        $input = $request->getParsedBody();

        if (!is_array($input)) {
            return sendError($this->responseFactory, 400, 'Malformed JSON payload.');
        }

        // Get the email and password
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        // Email and password fields check
        if (!is_string($email) || trim($email) === '' || !is_string($password) || $password === '') {
            return sendError(
                $this->responseFactory,
                422,
                'Email and password are required.'
            );
        }

        // Find the user by email from database
        $user = $this->usersModel->findUserByEmail($email);

        if ($user === false || !is_string($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return sendError(
                $this->responseFactory,
                401,
                'Invalid email or password.'
            );
        }

        // Start and regenerate the session with new ID
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        // Store user id and role in SESSION superglobal
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Return success response in JSON payload
        $response->getBody()->write(json_encode([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]));

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }
}
