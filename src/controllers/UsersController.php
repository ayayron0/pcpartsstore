<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

class UsersController
{
    private UsersModel $usersModel;
    private ResponseFactory $responseFactory;

    public function __construct(
        UsersModel $usersModel,
        ResponseFactory $responseFactory
    ) {
        $this->usersModel = $usersModel;
        $this->responseFactory = $responseFactory;
    }

    // GET /users
    public function index(Request $request, Response $response): Response
    {
        //Read pagination and filter paramters
        $page = ($_GET['page'] ?? 1);
        $limit = ($_GET['limit'] ?? 5);
        $name = $_GET['name'] ?? null;

        // Read sorting parameters
        $sort = $_GET['sort'] ?? 'id';
        $direction = $_GET['direction'] ?? 'asc';

        //reject unsupported query params
        $allowedParams = ['page', 'limit', 'name', 'sort', 'direction'];

        foreach ($_GET as $key => $value) {
            if (!in_array($key, $allowedParams)) {
                return sendError(
                    $this->responseFactory,
                    400,
                    'Invalid query parameter'
                );
            }
        }

        // Allow only supported sorting fields and directions
        $allowedSortFields = ['id', 'first_name', 'last_name'];
        $allowedDirections = ['asc', 'desc'];

        if (
            !in_array($sort, $allowedSortFields, true) ||
            !in_array($direction, $allowedDirections, true)) {

            return sendError(
                $this->responseFactory,
                400,
                'Invalid sorting parameter'
            );

        }

        //validate page number and page size
        if (
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            $page < 1 ||
            $page > 100 ||
            filter_var($limit, FILTER_VALIDATE_INT) === false ||
            $limit < 1 ||
            $limit > 50
        ) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid pagination'
            );
        }

        $offset = ($page - 1) * $limit; //how many rows to skip

        //validate name filter
        if ($name !== null && !is_string($name)) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid name filter'
            );
        }

        //optional first-name or last-name filter
        $whereSql = '';
        $queryParams = [];

        if ($name !== null && trim($name) !== '') {
            $whereSql = 'WHERE first_name LIKE :first_name OR last_name LIKE :last_name';
            $queryParams['first_name'] = '%' . trim($name) . '%';
            $queryParams['last_name'] = '%' . trim($name) . '%';
        }

        //count matching records before pagination
        $totalItems = $this->usersModel->countUsers($whereSql, $queryParams);
        $totalPages = (int) ceil($totalItems / $limit);

        //fetch one page in a consistent ID order
        $users = $this->usersModel->getUsersPage(
            $whereSql,
            $queryParams,
            $limit,
            (int) $offset,
            $sort,
            $direction
        );

        $response->getBody()->write(
            json_encode([
                'metadata' => [
                    'total_items' => $totalItems,
                    'total_pages' => $totalPages,
                    'current_page' => (int) $page,
                    'per_page' => (int) $limit
                ],
                'data' => $users
            ])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    // GET /users/{id}
    public function show(
        Request $request,
        Response $response,
        array $args
    ): Response {
        // Require a positive integer user ID
        $userId = filter_var($args['id'], FILTER_VALIDATE_INT);

        if ($userId === false || $userId < 1) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid user ID'
            );
        }

        $user = $this->usersModel->findUser($args['id']);

        if (!$user) {
            return sendError(
                $this->responseFactory,
                404,
                'User not found'
            );
        }

        $response->getBody()->write(json_encode($user));

        return $response->withHeader('Content-Type', 'application/json');
    }

    // POST /users
    public function store(Request $request, Response $response): Response
    {
        // Read the JSON body parsed by Slim middleware
        $data = $request->getParsedBody();

        // Reject a missing or malformed JSON body
        if (!is_array($data)) {
            return sendError(
                $this->responseFactory,
                400,
                'Malformed JSON payload.'
            );
        }

        // Check required user fields
        if (!isset($data['first_name'], $data['last_name'], $data['email'])) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['fields' => 'First name, last name and email are required.']
            );
        }

        foreach (['first_name' => 100, 'last_name' => 100, 'email' => 255] as $field => $maxLength) {
            if (!is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen(trim($data[$field])) > $maxLength) {
                return sendError(
                    $this->responseFactory,
                    422,
                    'Validation Failed',
                    [$field => "Must be a non-empty string of at most $maxLength characters."]
                );
            }

            $data[$field] = trim($data[$field]);
        }

        // Validate the email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['email' => 'A valid email address is required.']
            );
        }

        try {
            $id = $this->usersModel->createUser($data);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }

            return sendError(
                $this->responseFactory,
                409,
                'Email already exists'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'New user added', 'id' => $id])
        );

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    // PATCH /users/{id}
    public function patch(
        Request $request,
        Response $response,
        array $args
    ): Response {
        // Require a positive integer user ID
        $userId = filter_var($args['id'], FILTER_VALIDATE_INT);

        if ($userId === false || $userId < 1) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid user ID'
            );
        }

        // Read the JSON body parsed by Slim middleware
        $data = $request->getParsedBody();

        // Reject a missing or malformed JSON body
        if (!is_array($data)) {
            return sendError(
                $this->responseFactory,
                400,
                'Malformed JSON payload.'
            );
        }

        // Require at least one supported user field
        if (
            !$data ||
            array_diff(array_keys($data), ['first_name', 'last_name', 'email'])
        ) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['fields' => 'Provide first_name, last_name or email only.']
            );
        }

        $updates = [];
        $params = ['id' => $args['id']];

        foreach (['first_name' => 100, 'last_name' => 100, 'email' => 255] as $field => $maxLength) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (!is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen(trim($data[$field])) > $maxLength) {
                return sendError(
                    $this->responseFactory,
                    422,
                    'Validation Failed',
                    [$field => "Must be a non-empty string of at most $maxLength characters."]
                );
            }

            $params[$field] = trim($data[$field]);
            $updates[] = "$field = :$field";
        }

        // Validate the email format when supplied
        if (isset($params['email']) && !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['email' => 'A valid email address is required.']
            );
        }

        // Return 404 if the user does not exist
        if (!$this->usersModel->userExists($args['id'])) {
            return sendError(
                $this->responseFactory,
                404,
                'User not found'
            );
        }

        try {
            $this->usersModel->updateUser($updates, $params);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }

            return sendError(
                $this->responseFactory,
                409,
                'Email already exists'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'User updated successfully'])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    // DELETE /users/{id}
    public function delete(
        Request $request,
        Response $response,
        array $args
    ): Response {
        // Require a positive integer user ID
        $userId = filter_var($args['id'], FILTER_VALIDATE_INT);

        if ($userId === false || $userId < 1) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid user ID'
            );
        }

        try {
            $deletedRows = $this->usersModel->deleteUser($args['id']);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1451) {
                throw $e;
            }

            return sendError(
                $this->responseFactory,
                409,
                'Cannot remove a user with orders'
            );
        }

        // Return 404 if no user was deleted
        if ($deletedRows === 0) {
            return sendError(
                $this->responseFactory,
                404,
                'User not found'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'User removed successfully'])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }
}