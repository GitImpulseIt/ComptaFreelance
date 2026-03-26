<?php

declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    public function handle(): bool
    {
        // TODO: Vérifier que l'utilisateur est connecté via la session
        return isset($_SESSION['user_id']);
    }

    public function getUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}
