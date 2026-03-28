<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class CompteBancaireRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAllByEntreprise(int $entrepriseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM comptes_bancaires WHERE entreprise_id = :entreprise_id AND active = TRUE ORDER BY nom"
        );
        $stmt->execute(['entreprise_id' => $entrepriseId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM comptes_bancaires WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO comptes_bancaires (entreprise_id, nom, banque, iban, type_connexion)
             VALUES (:entreprise_id, :nom, :banque, :iban, :type_connexion)"
        );
        $stmt->execute([
            'entreprise_id' => $data['entreprise_id'],
            'nom' => $data['nom'],
            'banque' => $data['banque'] ?? '',
            'iban' => $data['iban'] ?? '',
            'type_connexion' => $data['type_connexion'] ?? 'manuel',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE comptes_bancaires SET nom = :nom, banque = :banque, iban = :iban, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'banque' => $data['banque'] ?? '',
            'iban' => $data['iban'] ?? '',
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE comptes_bancaires SET active = FALSE, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
