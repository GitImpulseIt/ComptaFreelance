<?php

declare(strict_types=1);

namespace Admin\Service;

use PDO;

class EntrepriseService
{
    public function __construct(private PDO $pdo) {}

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM users WHERE entreprise_id = e.id) as nb_users,
                    (SELECT COUNT(*) FROM factures WHERE entreprise_id = e.id) as nb_factures,
                    (SELECT COALESCE(SUM(montant_ttc), 0) FROM factures WHERE entreprise_id = e.id AND statut = \'payee\') as ca_total
             FROM entreprises e
             ORDER BY e.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM users WHERE entreprise_id = e.id) as nb_users,
                    (SELECT COUNT(*) FROM factures WHERE entreprise_id = e.id) as nb_factures,
                    (SELECT COALESCE(SUM(montant_ttc), 0) FROM factures WHERE entreprise_id = e.id AND statut = \'payee\') as ca_total,
                    (SELECT COUNT(*) FROM comptes_bancaires WHERE entreprise_id = e.id) as nb_comptes_bancaires
             FROM entreprises e
             WHERE e.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO entreprises (raison_sociale, siret, adresse, code_postal, ville, telephone, email, regime_tva)
             VALUES (:raison_sociale, :siret, :adresse, :code_postal, :ville, :telephone, :email, :regime_tva)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE entreprises SET
                raison_sociale = :raison_sociale, siret = :siret, adresse = :adresse,
                code_postal = :code_postal, ville = :ville, telephone = :telephone,
                email = :email, regime_tva = :regime_tva, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($data);
    }

    public function suspend(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE entreprises SET active = NOT active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
