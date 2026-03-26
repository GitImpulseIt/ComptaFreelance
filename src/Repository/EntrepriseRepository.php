<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class EntrepriseRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(): array { return []; }
    public function findById(int $id): ?array { return null; }
    public function create(array $data): int { return 0; }
    public function update(int $id, array $data): void {}
    public function suspend(int $id): void {}
}
