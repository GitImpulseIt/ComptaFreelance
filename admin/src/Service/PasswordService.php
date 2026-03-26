<?php

declare(strict_types=1);

namespace Admin\Service;

use PDO;

class PasswordService
{
    public function __construct(private PDO $pdo) {}

    public function changeAdminPassword(string $currentPassword, string $newPassword): bool
    {
        $storedHash = $this->getStoredHash();
        $currentHash = hash('sha256', $currentPassword);

        if (!hash_equals($storedHash, $currentHash)) {
            return false;
        }

        $newHash = hash('sha256', $newPassword);
        $stmt = $this->pdo->prepare("UPDATE admin_settings SET value = :hash, updated_at = NOW() WHERE key = 'password_hash'");
        $stmt->execute(['hash' => $newHash]);
        return true;
    }

    public function generateRandomPassword(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        return $password;
    }

    private function getStoredHash(): string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM admin_settings WHERE key = 'password_hash'");
        $stmt->execute();
        return $stmt->fetchColumn() ?: '';
    }
}
