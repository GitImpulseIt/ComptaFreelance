<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class PlanComptablePrefRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<string, array{enabled:?bool, libelle_source:?string, libelle_perso:?string}>
     */
    public function findAllByEntreprise(int $entrepriseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT numero, enabled, libelle_source, libelle_perso
             FROM plan_comptable_pref
             WHERE entreprise_id = :eid"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['numero']] = [
                'enabled' => $row['enabled'] === null ? null : (bool) $row['enabled'],
                'libelle_source' => $row['libelle_source'],
                'libelle_perso' => $row['libelle_perso'],
            ];
        }
        return $map;
    }

    /**
     * Upsert : si tous les champs sont nuls (retour au défaut), on supprime
     * l'enregistrement pour garder la table légère.
     */
    public function upsert(int $entrepriseId, string $numero, ?bool $enabled, ?string $source, ?string $libellePerso): void
    {
        if ($enabled === null && $source === null && $libellePerso === null) {
            $this->pdo->prepare(
                "DELETE FROM plan_comptable_pref WHERE entreprise_id = :eid AND numero = :n"
            )->execute(['eid' => $entrepriseId, 'n' => $numero]);
            return;
        }
        // PostgreSQL exige BOOL explicite ; PDO convertit false en '' qui est rejeté.
        $stmt = $this->pdo->prepare(
            "INSERT INTO plan_comptable_pref (entreprise_id, numero, enabled, libelle_source, libelle_perso)
             VALUES (:eid, :n, :e, :s, :lp)
             ON CONFLICT (entreprise_id, numero) DO UPDATE
             SET enabled = EXCLUDED.enabled,
                 libelle_source = EXCLUDED.libelle_source,
                 libelle_perso = EXCLUDED.libelle_perso,
                 updated_at = now()"
        );
        $stmt->bindValue(':eid', $entrepriseId, PDO::PARAM_INT);
        $stmt->bindValue(':n', $numero);
        if ($enabled === null) {
            $stmt->bindValue(':e', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':e', $enabled ? 't' : 'f');
        }
        $stmt->bindValue(':s', $source);
        $stmt->bindValue(':lp', $libellePerso);
        $stmt->execute();
    }
}
