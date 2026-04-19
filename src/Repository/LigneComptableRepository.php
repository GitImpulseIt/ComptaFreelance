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

    public function findDistinctComptes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT compte FROM lignes_comptables ORDER BY compte"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function findDistinctComptesByEntreprise(int $entrepriseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT l.compte
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
             ORDER BY l.compte"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
