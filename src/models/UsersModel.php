<?php

class UsersModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countUsers(string $whereSql, array $queryParams): int
    {
        //count matching records before pagination
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users $whereSql"
        );
        $countStmt->execute($queryParams);

        return (int) $countStmt->fetchColumn();
    }

    public function getUsersPage(
        string $whereSql,
        array $queryParams,
        int|string $limit,
        int $offset,
        string $sort,
        string $direction
    ): array {
        //fetch one page in a consistent ID order
        $stmt = $this->pdo->prepare(
            "SELECT id, first_name, last_name, email
            FROM users $whereSql
            ORDER BY $sort $direction, id ASC
            LIMIT :limit OFFSET :offset"
        );

        //Bind filter values and pagination integers safely
        foreach ($queryParams as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findUser(string $userId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, first_name, last_name, email FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch();
    }

    public function createUser(array $data): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (first_name, last_name, email)
             VALUES (:first_name, :last_name, :email)'
        );
        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email']
        ]);

        return $this->pdo->lastInsertId();
    }

    public function userExists(string $userId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch();
    }

    public function updateUser(array $updates, array $params): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id'
        );
        $stmt->execute($params);
    }

    public function deleteUser(string $userId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->rowCount();
    }
}