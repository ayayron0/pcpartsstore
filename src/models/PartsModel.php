<?php

class PartsModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countParts(string $whereSql, array $queryParams): int
    {
        $countSql = "SELECT COUNT(*) AS total FROM parts $whereSql";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($queryParams);

        return (int) $countStmt->fetchColumn();
    }

    public function getPartsPage(
        string $whereSql,
        array $queryParams,
        int $limit,
        int $offset,
        string $sort,
        string $direction
    ): array {
        $dataSql = "SELECT * FROM parts $whereSql
                ORDER BY $sort $direction, id ASC
                LIMIT :limit OFFSET :offset";
        $dataStmt = $this->pdo->prepare($dataSql);

        $dataStmt->execute(array_merge($queryParams, [
            'limit' => $limit,
            'offset' => $offset,
        ]));

        return $dataStmt->fetchAll();
    }

    public function findPart(string $partId): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM parts WHERE id = :id"
        );

        $stmt->execute([
            'id' => $partId
        ]);

        return $stmt->fetch();
    }

    public function createPart(array $input): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO parts (name, category, brand_id, price)
             VALUES (:name, :category, :brand_id, :price)'
        );

        $stmt->execute([
            'name' => trim($input['name']),
            'category' => trim($input['category']),
            'brand_id' => $input['brand_id'],
            'price' => $input['price'],
        ]);

        return $this->pdo->lastInsertId();
    }

    public function deletePart(string $partId): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM parts WHERE id = :id"
        );

        $stmt->execute([
            'id' => $partId
        ]);

        return $stmt->rowCount();
    }

    public function updatePartPrice(string $partId, mixed $price): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE parts
             SET price = :price
             WHERE id = :id"
        );

        $stmt->execute([
            'price' => $price,
            'id' => $partId
        ]);

        return $stmt->rowCount();
    }

    public function findPartId(string $partId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM parts WHERE id = :id'
        );

        $stmt->execute([
            'id' => $partId
        ]);

        return $stmt->fetch();
    }

    public function replacePart(string $partId, array $input): void
    {
        $putStmt = $this->pdo->prepare(
            "UPDATE parts
             SET name = :name, category = :category,
                 brand_id = :brand_id, price = :price
             WHERE id = :id"
        );

        $putStmt->execute([
            'name' => trim($input['name']),
            'category' => trim($input['category']),
            'brand_id' => $input['brand_id'],
            'price' => $input['price'],
            'id' => $partId
        ]);
    }
}
