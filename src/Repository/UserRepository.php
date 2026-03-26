<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array { return null; }
    public function findByEmail(string $email): ?array { return null; }
    public function create(array $data): int { return 0; }
    public function update(int $id, array $data): void {}
}
