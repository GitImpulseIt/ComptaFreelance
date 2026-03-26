<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, e.raison_sociale as entreprise_nom
             FROM users u
             JOIN entreprises e ON e.id = u.entreprise_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, e.raison_sociale as entreprise_nom
             FROM users u
             JOIN entreprises e ON e.id = u.entreprise_id
             WHERE u.email = :email'
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (entreprise_id, email, password, nom, prenom)
             VALUES (:entreprise_id, :email, :password, :nom, :prenom)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email = :email, nom = :nom, prenom = :prenom, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute($data);
    }
}
