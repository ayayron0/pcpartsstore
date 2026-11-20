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
            !in_array($direction, $allowedDirections, true)
        ) {

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

        // Customers can only see their own orders.
        // Admins can see all orders or use the user_id filter.
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $whereClauses[] = 'user_id = :authenticated_user_id';
            $queryParams['authenticated_user_id'] = (int) $_SESSION['user_id'];
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

        // Ownership check
        if (
            ($_SESSION['role'] ?? '') !== 'admin' &&
            (int) $order['user_id'] !== (int) $_SESSION['user_id']
        ) {
            return sendError(
                $this->responseFactory,
                403, // Unauthorized
                'You are not allowed to access this order.'
            );
        }

    // Read items only after the ownership check above has passed.
    $order['items'] = $this->ordersModel->getOrderItems(
         (string) $orderId
    );

    $order['currency'] = 'CAD';

    $order['total'] = $this->ordersModel->getOrderTotal(
        (string) $orderId
    );

    $response->getBody()->write(json_encode($order));

        return $response->withHeader('Content-Type', 'application/json');
    }


    // POST /orders
public function store(Request $request, Response $response): Response
{
    $data = $request->getParsedBody();

    if (!is_array($data)) {
        return sendError(
            $this->responseFactory,
            400,
            'Malformed JSON payload.'
        );
    }

    // The server supplies ownership, prices and order status.
    if (array_diff(array_keys($data), ['items', 'checkout_key'])) {
        return sendError(
            $this->responseFactory,
            422,
            'Validation Failed',
            ['fields' => 'Provide items and checkout_key only.']
        );
    }

    $checkoutKey = $data['checkout_key'] ?? null;

    if (
        !is_string($checkoutKey)
        || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $checkoutKey)
    ) {
        return sendError(
            $this->responseFactory,
            422,
            'Validation Failed',
            [
                'checkout_key' =>
                    'Use 16–64 letters, numbers, underscores or hyphens.'
            ]
        );
    }

    $items = $data['items'] ?? null;

    if (
        !is_array($items)
        || !array_is_list($items)
        || count($items) < 1
        || count($items) > 50
    ) {
        return sendError(
            $this->responseFactory,
            422,
            'Validation Failed',
            ['items' => 'Provide a list containing 1–50 cart items.']
        );
    }

    $validatedItems = [];
    $seenParts = [];

    foreach ($items as $index => $item) {
        if (
            !is_array($item)
            || array_diff(array_keys($item), ['part_id', 'quantity'])
            || !isset($item['part_id'], $item['quantity'])
            || !is_int($item['part_id'])
            || $item['part_id'] < 1
            || !is_int($item['quantity'])
            || $item['quantity'] < 1
            || $item['quantity'] > 99
        ) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                [
                    "items.$index" =>
                        'Provide a positive integer part_id and quantity from 1–99.'
                ]
            );
        }

        if (isset($seenParts[$item['part_id']])) {
            return sendError(
                $this->responseFactory,
                422,
                'Validation Failed',
                ['items' => 'Each part may appear only once. Adjust its quantity.']
            );
        }

        $seenParts[$item['part_id']] = true;

        $validatedItems[] = [
            'part_id' => $item['part_id'],
            'quantity' => $item['quantity'],
        ];
    }

    try {
        $result = $this->ordersModel->createCheckout(
            (int) $_SESSION['user_id'],
            $validatedItems,
            $checkoutKey
        );
    } catch (DomainException $exception) {
        return sendError(
            $this->responseFactory,
            409,
            $exception->getMessage()
        );
    } catch (PDOException $exception) {
        $databaseCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($databaseCode === 1452) {
            return sendError(
                $this->responseFactory,
                409,
                'An account or cart item is no longer available.'
            );
        }

        if (in_array($databaseCode, [1205, 1213], true)) {
            return sendError(
                $this->responseFactory,
                503,
                'Checkout is temporarily busy. Retry with the same checkout key.'
            )->withHeader('Retry-After', '1');
        }

        throw $exception;
    }

    $response->getBody()->write(json_encode([
        'message' => $result['replayed']
            ? 'Checkout already completed. Returning the existing order.'
            : 'Order placed successfully.',
        'id' => $result['id'],
        'replayed' => $result['replayed'],
    ]));

    return $response
        ->withStatus($result['replayed'] ? 200 : 201)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Cache-Control', 'no-store');
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

        // Only admins can change an order's status.
        if (($_SESSION['role'] ?? '') !== 'admin') {
            return sendError(
                $this->responseFactory,
                403,
                'Only admins can change order status.'
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

        $order = $this->ordersModel->findOrder($orderId); // Find order of the user

        if ($order === false) {
            return sendError($this->responseFactory, 404, 'Order not found');
        }

        // Customers can delete their own orders
        // Admins can delete any order.
        if (
            ($_SESSION['role'] ?? '') !== 'admin' &&
            (int) $order['user_id'] !== (int) $_SESSION['user_id']
        ) {
            return sendError(
                $this->responseFactory,
                403,
                'You are not allowed to delete this order.'
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
