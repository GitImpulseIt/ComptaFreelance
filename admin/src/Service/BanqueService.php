<?php

declare(strict_types=1);

namespace Admin\Service;

use PDO;

class BanqueService
{
    public function __construct(private PDO $pdo) {}

    public function findAllComptes(): array
    {
        $stmt = $this->pdo->query(
            'SELECT cb.*, e.raison_sociale as entreprise_nom,
                    (SELECT COUNT(*) FROM transactions_bancaires WHERE compte_bancaire_id = cb.id) as nb_transactions,
                    (SELECT COUNT(*) FROM imports_bancaires WHERE compte_bancaire_id = cb.id) as nb_imports
             FROM comptes_bancaires cb
             JOIN entreprises e ON e.id = cb.entreprise_id
             ORDER BY cb.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function findCompteById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cb.*, e.raison_sociale as entreprise_nom
             FROM comptes_bancaires cb
             JOIN entreprises e ON e.id = cb.entreprise_id
             WHERE cb.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findImportsByCompte(int $compteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM imports_bancaires WHERE compte_bancaire_id = :id ORDER BY created_at DESC'
        );
        $stmt->execute(['id' => $compteId]);
        return $stmt->fetchAll();
    }
}
