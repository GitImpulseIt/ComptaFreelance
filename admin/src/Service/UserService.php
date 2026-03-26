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
             JOIN entreprises e ON e.id = u.entreprise_id
             ORDER BY e.raison_sociale, u.nom, u.prenom'
        );
        return $stmt->fetchAll();
    }

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

    public function findByEntreprise(int $entrepriseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE entreprise_id = :id ORDER BY nom, prenom'
        );
        $stmt->execute(['id' => $entrepriseId]);
        return $stmt->fetchAll();
    }

    public function create(array $data, string $plainPassword): int
    {
        $data['password'] = password_hash($plainPassword, PASSWORD_BCRYPT);
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
            'UPDATE users SET entreprise_id = :entreprise_id, email = :email,
                nom = :nom, prenom = :prenom, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($data);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];
        if ($excludeId) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function resetPassword(int $id): string
    {
        $newPassword = $this->passwordService->generateRandomPassword();
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['password' => $hashedPassword, 'id' => $id]);

        return $newPassword;
    }

    public function getAllEntreprises(): array
    {
        $stmt = $this->pdo->query('SELECT id, raison_sociale FROM entreprises WHERE active = true ORDER BY raison_sociale');
        return $stmt->fetchAll();
    }
}
