<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class LienDocumentRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByTransaction(int $transactionId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM liens_documents WHERE transaction_bancaire_id = :tid ORDER BY created_at"
        );
        $stmt->execute(['tid' => $transactionId]);
        return $stmt->fetchAll();
    }

    public function create(int $transactionId, string $url): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO liens_documents (transaction_bancaire_id, url) VALUES (:tid, :url)"
        );
        $stmt->execute(['tid' => $transactionId, 'url' => $url]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM liens_documents WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM liens_documents WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
