<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class PlanComptableSimplifieRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array{numero: string, libelle: string, categorie: string, sens: string}>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT numero, libelle, categorie, sens
             FROM plan_comptable_simplifie
             ORDER BY numero"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
