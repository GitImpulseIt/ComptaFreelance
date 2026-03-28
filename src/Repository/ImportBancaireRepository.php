<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class ImportBancaireRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAllByCompte(int $compteId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM imports_bancaires WHERE compte_bancaire_id = :compte_id ORDER BY created_at DESC"
        );
        $stmt->execute(['compte_id' => $compteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM imports_bancaires WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO imports_bancaires (compte_bancaire_id, source, format, fichier, statut)
             VALUES (:compte_bancaire_id, :source, :format, :fichier, :statut)"
        );
        $stmt->execute([
            'compte_bancaire_id' => $data['compte_bancaire_id'],
            'source' => $data['source'] ?? 'fichier',
            'format' => $data['format'] ?? '',
            'fichier' => $data['fichier'] ?? null,
            'statut' => 'en_cours',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut, int $nbTransactions): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE imports_bancaires SET statut = :statut, nb_transactions = :nb WHERE id = :id"
        );
        $stmt->execute([
            'id' => $id,
            'statut' => $statut,
            'nb' => $nbTransactions,
        ]);
    }
}
