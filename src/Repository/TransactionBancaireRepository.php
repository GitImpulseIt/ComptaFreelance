<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class TransactionBancaireRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAllByCompte(int $compteId): array { return []; }
    public function findNonRapprochees(int $entrepriseId): array { return []; }
    public function findById(int $id): ?array { return null; }
    public function createBatch(array $transactions): int { return 0; }
    public function rapprocher(int $id, ?int $factureId, ?int $depenseId): void {}
}
