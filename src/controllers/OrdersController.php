<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

class OrdersController
{
    private OrdersModel $ordersModel;
    private ResponseFactory $responseFactory;

    public function __construct(
        OrdersModel $ordersModel,
        ResponseFactory $responseFactory
    ) {
        $this->ordersModel = $ordersModel;
        $this->responseFactory = $responseFactory;
    }

    // GET /orders
    public function index(Request $request, Response $response): Response
    {
        //Read pagination and filter paramters
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 5;
        $user_id = $_GET['user_id'] ?? null;
        $status = $_GET['status'] ?? null;

       // Read sorting parameters
        $sort = $_GET['sort'] ?? 'id';
        $direction = $_GET['direction'] ?? 'asc';

        //reject unsupported query params
        $allowedParams = ['page', 'limit', 'user_id', 'status', 'sort', 'direction'];

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
        $allowedSortFields = ['id', 'user_id', 'status'];
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

        //validate user ID filter
        if ($user_id !== null && (filter_var($user_id, FILTER_VALIDATE_INT) === false || $user_id < 1)) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid user ID'
            );
        }

        //validate order status filter
        $allowedStatuses = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];

        if ($status !== null && !in_array($status, $allowedStatuses, true)) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid order status'
            );
        }

        //build optional filters, the supplied filters must both match
        $whereClauses = [];
        $queryParams = [];

        if ($user_id !== null) {
            $whereClauses[] = 'user_id = :user_id';
            $queryParams['user_id'] = (int) $user_id;
        }

        if ($status !== null) {
            $whereClauses[] = 'status = :status';
            $queryParams['status'] = $status;
        }

        $whereSql = '';

        if (count($whereClauses) > 0) {
            $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
        }

        //count matching records before pagination
        $totalItems = $this->ordersModel->countOrders(
            $whereSql,
            $queryParams
        );
        $totalPages = (int) ceil($totalItems / $limit);

        //fetch one page in a consistent ID order
        $orders = $this->ordersModel->getOrdersPage(
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
                'data' => $orders
            ])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    // GET /orders/{id}
    public function show(
        Request $request,
        Response $response,
        array $args
    ): Response {
        // Require a positive integer order ID
        $orderId = filter_var($args['id'], FILTER_VALIDATE_INT);

        if ($orderId === false || $orderId < 1) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid order ID'
            );
        }

        $order = $this->ordersModel->findOrder($args['id']);

        if (!$order) {
            return sendError(
                $this->responseFactory,
                404,
                'Order not found'
            );
        }

        $response->getBody()->write(json_encode($order));

        return $response->withHeader('Content-Type', 'application/json');
    }

    // POST /orders
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

        // Require a positive integer user ID
        if (
            !isset($data['user_id']) ||
            !is_int($data['user_id']) ||
            $data['user_id'] < 1
        ) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['user_id' => 'A positive integer user_id is required.']
            );
        }

        $status = array_key_exists('status', $data) ? $data['status'] : 'Processing';

        // Validate the order status
        if (!in_array($status, ['Processing', 'Shipped', 'Delivered', 'Cancelled'], true)) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['status' => 'Status must be Processing, Shipped, Delivered or Cancelled.']
            );
        }

        try {
            $id = $this->ordersModel->createOrder($data['user_id'], $status);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1452) {
                throw $e;
            }

            return sendError(
                $this->responseFactory,
                404,
                'User not found'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'New order added', 'id' => $id])
        );

        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    // PATCH /orders/{id}
    public function patch(
        Request $request,
        Response $response,
        array $args
    ): Response {
        // Require a positive integer order ID
        $orderId = filter_var($args['id'], FILTER_VALIDATE_INT);

        if ($orderId === false || $orderId < 1) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid order ID'
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

        // Accept only a valid status field
        if (
            !isset($data['status']) ||
            array_diff(array_keys($data), ['status']) ||
            !in_array($data['status'], ['Processing', 'Shipped', 'Delivered', 'Cancelled'], true)
        ) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['status' => 'Provide only status: Processing, Shipped, Delivered or Cancelled.']
            );
        }

        // Return 404 if the order does not exist
        if (!$this->ordersModel->orderExists($args['id'])) {
            return sendError(
                $this->responseFactory,
                404,
                'Order not found'
            );
        }

        $this->ordersModel->updateOrderStatus(
            $args['id'],
            $data['status']
        );

        $response->getBody()->write(
            json_encode(['message' => 'Order status updated successfully'])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    // DELETE /orders/{id}
    public function delete(
        Request $request,
        Response $response,
        array $args
    ): Response {
        // Require a positive integer order ID
        $orderId = filter_var($args['id'], FILTER_VALIDATE_INT);

        if ($orderId === false || $orderId < 1) {
            return sendError(
                $this->responseFactory,
                400,
                'Invalid order ID'
            );
        }

        try {
            $deletedRows = $this->ordersModel->deleteOrder($args['id']);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1451) {
                throw $e;
            }

            return sendError(
                $this->responseFactory,
                409,
                'Cannot remove an order with items'
            );
        }

        // Return 404 if no order was deleted
        if ($deletedRows === 0) {
            return sendError(
                $this->responseFactory,
                404,
                'Order not found'
            );
        }

        $response->getBody()->write(
            json_encode(['message' => 'Order removed successfully'])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }
}