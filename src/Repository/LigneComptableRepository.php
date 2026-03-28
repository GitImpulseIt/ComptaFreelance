<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class LigneComptableRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByTransaction(int $transactionId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM lignes_comptables WHERE transaction_bancaire_id = :tid ORDER BY id"
        );
        $stmt->execute(['tid' => $transactionId]);
        return $stmt->fetchAll();
    }

    public function replaceForTransaction(int $transactionId, array $lignes): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("DELETE FROM lignes_comptables WHERE transaction_bancaire_id = :tid");
            $stmt->execute(['tid' => $transactionId]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO lignes_comptables (transaction_bancaire_id, compte, montant_ht, type, tva)
                 VALUES (:tid, :compte, :montant_ht, :type, :tva)"
            );

            foreach ($lignes as $ligne) {
                $stmt->execute([
                    'tid' => $transactionId,
                    'compte' => $ligne['compte'],
                    'montant_ht' => $ligne['montant_ht'],
                    'type' => $ligne['type'],
                    'tva' => $ligne['tva'],
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
