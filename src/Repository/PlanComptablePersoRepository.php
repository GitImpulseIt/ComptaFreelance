<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class PlanComptablePersoRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array{numero:string,libelle:string,sens:string,categorie:?string}> */
    public function findAllByEntreprise(int $entrepriseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT numero, libelle, sens, categorie
             FROM plan_comptable_perso
             WHERE entreprise_id = :eid
             ORDER BY numero"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByNumero(int $entrepriseId, string $numero): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM plan_comptable_perso WHERE entreprise_id = :eid AND numero = :n"
        );
        $stmt->execute(['eid' => $entrepriseId, 'n' => $numero]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $entrepriseId, string $numero, string $libelle, string $sens, ?string $categorie): void
    {
        $this->pdo->prepare(
            "INSERT INTO plan_comptable_perso (entreprise_id, numero, libelle, sens, categorie)
             VALUES (:eid, :n, :l, :s, :c)"
        )->execute(['eid' => $entrepriseId, 'n' => $numero, 'l' => $libelle, 's' => $sens, 'c' => $categorie]);
    }

    public function update(int $entrepriseId, string $numero, string $libelle, string $sens, ?string $categorie): void
    {
        $this->pdo->prepare(
            "UPDATE plan_comptable_perso
             SET libelle = :l, sens = :s, categorie = :c, updated_at = now()
             WHERE entreprise_id = :eid AND numero = :n"
        )->execute(['eid' => $entrepriseId, 'n' => $numero, 'l' => $libelle, 's' => $sens, 'c' => $categorie]);
    }

    public function delete(int $entrepriseId, string $numero): void
    {
        $this->pdo->prepare(
            "DELETE FROM plan_comptable_perso WHERE entreprise_id = :eid AND numero = :n"
        )->execute(['eid' => $entrepriseId, 'n' => $numero]);
    }
}
