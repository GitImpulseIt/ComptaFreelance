<?php

declare(strict_types=1);

namespace App\Service\Banque;

class ConnectService
{
    public function __construct(private array $config) {}

    public function connecterCompte(int $entrepriseId, string $redirectUrl): string
    {
        // TODO: Initier le flux OAuth avec le provider bancaire (Bridge, GoCardless…)
        return '';
    }

    public function synchroniser(int $compteId): int
    {
        // TODO: Récupérer les nouvelles transactions via l'API du provider
        return 0;
    }
}
