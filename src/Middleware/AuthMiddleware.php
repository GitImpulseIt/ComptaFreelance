<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repository\UserRepository;

class AuthMiddleware
{
    public function __construct(private UserRepository $userRepository) {}

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function getUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $_SESSION['user'] ?? null;
    }

    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function getEntrepriseId(): ?int
    {
        return $_SESSION['entreprise_id'] ?? null;
    }

    public function login(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['entreprise_id'] = $user['entreprise_id'];
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'entreprise_id' => $user['entreprise_id'],
            'entreprise_nom' => $user['entreprise_nom'] ?? '',
        ];
    }

    public function logout(): void
    {
        session_destroy();
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
