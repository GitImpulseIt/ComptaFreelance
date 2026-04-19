<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class PlanComptableRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Liste des comptes sélectionnables lors de la qualification :
     * exclut les optionnels, la classe 3 (stocks, rare en compta freelance),
     * et les rubriques de niveau 2 (numéros à 2 chiffres : 10, 11, 20, …
     * qui ne sont pas des comptes postables mais des têtes de section).
     *
     * @return list<array{numero: string, libelle: string, classe: int}>
     */
    public function findSelectable(): array
    {
        $stmt = $this->pdo->query(
            "SELECT numero, libelle, classe
             FROM plan_comptable
             WHERE optionnel = FALSE
               AND classe <> 3
               AND LENGTH(numero) > 2
             ORDER BY numero"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
