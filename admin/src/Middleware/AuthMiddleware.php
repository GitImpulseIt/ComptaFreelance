<?php

declare(strict_types=1);

namespace Admin\Middleware;

use PDO;

class AuthMiddleware
{
    public function __construct(private PDO $pdo) {}

    public function isAuthenticated(): bool
    {
        return ($_SESSION['admin_authenticated'] ?? false) === true;
    }

    public function authenticate(string $password): bool
    {
        $storedHash = $this->getStoredHash();
        if (!$storedHash) {
            return false;
        }

        $inputHash = hash('sha256', $password);
        if (hash_equals($storedHash, $inputHash)) {
            $_SESSION['admin_authenticated'] = true;
            return true;
        }
        return false;
    }

    public function getStoredHash(): ?string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM admin_settings WHERE key = 'password_hash'");
        $stmt->execute();
        return $stmt->fetchColumn() ?: null;
    }

    public function logout(): void
    {
        session_destroy();
    }
}
