<?php

class BrandsModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countBrands(string $whereSql, array $queryParams): int
    {
        // 3. Get Total Count (for metadata)s
        $countSql = "SELECT COUNT(*) AS total FROM brands $whereSql";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($queryParams);

        return (int)$countStmt->fetchColumn();
    }

    public function getBrandsPage(
        string $whereSql,
        array $queryParams,
        int $limit,
        int $offset
    ): array {
        // 4. Fetch the Paginated Data
        $dataSql = "SELECT * FROM brands $whereSql ORDER BY id ASC LIMIT :limit OFFSET :offset";
        $dataStmt = $this->pdo->prepare($dataSql);

        // Bind filter and pagination parameters
        $dataStmt->execute(array_merge($queryParams, [
            'limit' => $limit,
            'offset' => $offset
        ]));

        return $dataStmt->fetchAll();
    }

    public function findBrand(string $brandId): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM brands WHERE id = :id"
        );

        $stmt->execute([
            'id' => $brandId
        ]);

        return $stmt->fetch();
    }

    public function createBrand(array $input): string
    {
        // Save trimmed text values
        $stmt = $this->pdo->prepare("INSERT INTO brands (name) VALUES (:name)");

        $stmt->execute([
            'name' => trim($input['name'])
        ]);

        return $this->pdo->lastInsertId();
    }

    public function deleteBrand(string $brandId): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM brands WHERE id = :id"
        );

        $stmt->execute([
            'id' => $brandId
        ]);

        return $stmt->rowCount();
    }

    public function updateBrandName(string $brandId, mixed $name): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE brands
            SET name = :name
            WHERE id = :id"
        );

        $stmt->execute([
            'name' => $name,
            'id' => $brandId
        ]);

        return $stmt->rowCount();
    }

    public function findBrandId(string $brandId): array|false
    {
        // Check if the PC_part id exists
        $stmt = $this->pdo->prepare('SELECT id FROM brands WHERE id = :id');
        $stmt->execute(['id' => $brandId]);

        return $stmt->fetch();
    }

    public function replaceBrand(string $brandId, array $input): void
    {
        // Update parts based on inputs
        $putStmt = $this->pdo->prepare(
            "UPDATE brands SET name = :name WHERE id = :id"
        );

        $putStmt->execute([
            'name' => trim($input['name']),
            'id' => $brandId
        ]);
    }
}
