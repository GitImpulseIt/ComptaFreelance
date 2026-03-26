<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Repository\EntrepriseRepository;

class EntrepriseService
{
    public function __construct(private EntrepriseRepository $repository) {}

    public function creer(array $data): int
    {
        return $this->repository->create($data);
    }

    public function suspendre(int $id): void
    {
        $this->repository->suspend($id);
    }

    public function listerAvecStats(): array
    {
        // TODO: Retourner la liste des entreprises avec CA, nb factures, dernier import
        return [];
    }
}
