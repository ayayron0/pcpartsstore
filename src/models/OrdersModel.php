<?php

class OrdersModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countOrders(string $whereSql, array $queryParams): int
    {
        //count matching records before pagination
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM orders $whereSql"
        );
        $countStmt->execute($queryParams);

        return (int) $countStmt->fetchColumn();
    }

    public function getOrdersPage(
        string $whereSql,
        array $queryParams,
        int|string $limit,
        int $offset,
        string $sort,
        string $direction
    ): array {
        //fetch one page in a consistent ID order
        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, status
             FROM orders $whereSql
             ORDER BY $sort $direction, id ASC
             LIMIT :limit OFFSET :offset"
        );

        //Bind filter values and pagination integers safely
        foreach ($queryParams as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }

        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findOrder(string $orderId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, status FROM orders WHERE id = :id'
        );
        $stmt->execute(['id' => $orderId]);

        return $stmt->fetch();
    }

    public function createOrder(int $userId, string $status): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (user_id, status) VALUES (:user_id, :status)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status' => $status
        ]);

        return $this->pdo->lastInsertId();
    }

    public function orderExists(string $orderId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM orders WHERE id = :id'
        );
        $stmt->execute(['id' => $orderId]);

        return $stmt->fetch();
    }

    public function updateOrderStatus(string $orderId, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET status = :status WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'id' => $orderId
        ]);
    }

    public function deleteOrder(string $orderId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM orders WHERE id = :id'
        );
        $stmt->execute(['id' => $orderId]);

        return $stmt->rowCount();
    }

    public function createCheckout(
    int $userId,
    array $items,
    string $checkoutKey
): array {
    // Consistent ordering makes the same cart produce the same hash.
    usort(
        $items,
        fn(array $a, array $b) => $a['part_id'] <=> $b['part_id']
    );

    $requestHash = hash(
        'sha256',
        json_encode($items, JSON_THROW_ON_ERROR)
    );

    try {
        $this->pdo->beginTransaction();

        $orderStatement = $this->pdo->prepare(
            'INSERT INTO orders
                (user_id, status, checkout_key, request_hash)
             VALUES
                (:user_id, :status, :checkout_key, :request_hash)'
        );

        $orderStatement->execute([
            'user_id' => $userId,
            'status' => 'Processing',
            'checkout_key' => $checkoutKey,
            'request_hash' => $requestHash,
        ]);

        $orderId = $this->pdo->lastInsertId();

        // Lock each price against changes until checkout finishes.
        $partStatement = $this->pdo->prepare(
            'SELECT id, price
             FROM parts
             WHERE id = :id
             LOCK IN SHARE MODE'
        );

        $itemStatement = $this->pdo->prepare(
            'INSERT INTO order_items
                (order_id, part_id, quantity, unit_price)
             VALUES
                (:order_id, :part_id, :quantity, :unit_price)'
        );

        foreach ($items as $item) {
            $partStatement->execute(['id' => $item['part_id']]);
            $part = $partStatement->fetch();

            if (!$part) {
                throw new DomainException(
                    'Part ' . $item['part_id']
                    . ' is no longer available. Review your cart.'
                );
            }

            // Prices come from the database, never from the browser.
            if (
                !is_numeric($part['price'])
                || (float) $part['price'] < 0
            ) {
                throw new DomainException(
                    'A cart item has an invalid price.'
                );
            }

            $itemStatement->execute([
                'order_id' => $orderId,
                'part_id' => $part['id'],
                'quantity' => $item['quantity'],
                'unit_price' => $part['price'],
            ]);
        }

        $this->pdo->commit();

        return [
            'id' => $orderId,
            'replayed' => false,
        ];
    } catch (Throwable $exception) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        // A repeated checkout key must return the original order.
        if (
            $exception instanceof PDOException
            && (int) ($exception->errorInfo[1] ?? 0) === 1062
        ) {
            $existingStatement = $this->pdo->prepare(
                'SELECT id, request_hash
                 FROM orders
                 WHERE user_id = :user_id
                   AND checkout_key = :checkout_key'
            );

            $existingStatement->execute([
                'user_id' => $userId,
                'checkout_key' => $checkoutKey,
            ]);

            $existing = $existingStatement->fetch();

            if ($existing) {
                if (!hash_equals(
                    (string) $existing['request_hash'],
                    $requestHash
                )) {
                    throw new DomainException(
                        'This checkout key was already used for a different cart.'
                    );
                }

                return [
                    'id' => (string) $existing['id'],
                    'replayed' => true,
                ];
            }
        }

        throw $exception;
    }
}

public function getOrderItems(string $orderId): array
{
    $stmt = $this->pdo->prepare(
        'SELECT
            oi.part_id,
            p.name,
            oi.quantity,
            oi.unit_price,
            oi.unit_price * oi.quantity AS line_total
         FROM order_items AS oi
         JOIN parts AS p ON p.id = oi.part_id
         WHERE oi.order_id = :order_id
         ORDER BY oi.id ASC'
    );

    $stmt->execute(['order_id' => $orderId]);

    return $stmt->fetchAll();
}

public function getOrderTotal(string $orderId): ?string
{
    // Older orders have no recorded purchase prices.
    $stmt = $this->pdo->prepare(
        'SELECT
            CASE
                WHEN COUNT(*) > 0
                 AND COUNT(unit_price) = COUNT(*)
                THEN SUM(unit_price * quantity)
                ELSE NULL
            END AS total
         FROM order_items
         WHERE order_id = :order_id'
    );

    $stmt->execute(['order_id' => $orderId]);
    $total = $stmt->fetchColumn();

    return $total === null || $total === false
        ? null
        : (string) $total;
}

}