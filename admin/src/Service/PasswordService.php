<?php

declare(strict_types=1);

namespace Admin\Service;

class PasswordService
{
    private string $authConfigPath;

    public function __construct(string $authConfigPath)
    {
        $this->authConfigPath = $authConfigPath;
    }

    public function changeAdminPassword(string $currentPassword, string $newPassword, string $storedHash): bool
    {
        $currentHash = hash('sha256', $currentPassword);
        if (!hash_equals($storedHash, $currentHash)) {
            return false;
        }

        $newHash = hash('sha256', $newPassword);
        $this->writeHash($newHash);
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

    private function writeHash(string $hash): void
    {
        $content = "<?php\n\n"
            . "// Mot de passe admin hashé en SHA256\n"
            . "// Ce fichier est réécrit automatiquement lors du changement de mot de passe\n"
            . "return [\n"
            . "    'password_hash' => '" . $hash . "',\n"
            . "];\n";

        file_put_contents($this->authConfigPath, $content);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->authConfigPath, true);
        }
    }
}
