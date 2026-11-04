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
}