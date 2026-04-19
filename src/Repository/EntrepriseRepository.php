<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class EntrepriseRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM entreprises ORDER BY raison_sociale");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM entreprises WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO entreprises (raison_sociale, siret, adresse, code_postal, ville, telephone, email, regime_tva, statut_juridique, option_ir, option_ir_fin_exercice)
             VALUES (:raison_sociale, :siret, :adresse, :code_postal, :ville, :telephone, :email, :regime_tva, :statut_juridique, :option_ir, :option_ir_fin_exercice)"
        );
        $stmt->execute([
            'raison_sociale' => $data['raison_sociale'],
            'siret' => $data['siret'],
            'adresse' => $data['adresse'] ?? '',
            'code_postal' => $data['code_postal'] ?? '',
            'ville' => $data['ville'] ?? '',
            'telephone' => $data['telephone'] ?? '',
            'email' => $data['email'] ?? '',
            'regime_tva' => $data['regime_tva'] ?? 'franchise',
            'statut_juridique' => $data['statut_juridique'] ?? 'SASU',
            'option_ir' => $data['option_ir'] ?? false,
            'option_ir_fin_exercice' => $data['option_ir_fin_exercice'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE entreprises SET
                raison_sociale = :raison_sociale, siret = :siret, adresse = :adresse,
                code_postal = :code_postal, ville = :ville, telephone = :telephone,
                email = :email, regime_tva = :regime_tva, statut_juridique = :statut_juridique,
                option_ir = :option_ir, option_ir_fin_exercice = :option_ir_fin_exercice,
                regime_benefices = :regime_benefices, plan_comptable = :plan_comptable,
                updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $id,
            'raison_sociale' => $data['raison_sociale'],
            'siret' => $data['siret'],
            'adresse' => $data['adresse'] ?? '',
            'code_postal' => $data['code_postal'] ?? '',
            'ville' => $data['ville'] ?? '',
            'telephone' => $data['telephone'] ?? '',
            'email' => $data['email'] ?? '',
            'regime_tva' => $data['regime_tva'] ?? 'franchise',
            'statut_juridique' => $data['statut_juridique'] ?? 'SASU',
            'option_ir' => $data['option_ir'] ?? false,
            'option_ir_fin_exercice' => $data['option_ir_fin_exercice'] ?? null,
            'regime_benefices' => $data['regime_benefices'] ?? 'BNC',
            'plan_comptable' => in_array($data['plan_comptable'] ?? null, ['general', 'simplifie'], true)
                ? $data['plan_comptable']
                : 'simplifie',
        ]);
    }

    public function suspend(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE entreprises SET active = FALSE, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
