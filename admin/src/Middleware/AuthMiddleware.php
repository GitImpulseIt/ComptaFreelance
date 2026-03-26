<?php

declare(strict_types=1);

namespace Admin\Middleware;

class AuthMiddleware
{
    public function isAuthenticated(): bool
    {
        return ($_SESSION['admin_authenticated'] ?? false) === true;
    }

    public function authenticate(string $password, string $storedHash): bool
    {
        $inputHash = hash('sha256', $password);
        if (hash_equals($storedHash, $inputHash)) {
            $_SESSION['admin_authenticated'] = true;
            return true;
        }
        return false;
    }

    public function logout(): void
    {
        session_destroy();
    }
}
