<?php

declare(strict_types=1);

namespace Admin\Service;

use PDO;

class UserService
{
    public function __construct(
        private PDO $pdo,
        private PasswordService $passwordService,
    ) {}

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.*, e.raison_sociale as entreprise_nom
             FROM users u
             LEFT JOIN entreprises e ON e.id = u.entreprise_id
             ORDER BY u.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, e.raison_sociale as entreprise_nom
             FROM users u
             LEFT JOIN entreprises e ON e.id = u.entreprise_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function resetPassword(int $id): string
    {
        $newPassword = $this->passwordService->generateRandomPassword();
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['password' => $hashedPassword, 'id' => $id]);

        return $newPassword;
    }
}
