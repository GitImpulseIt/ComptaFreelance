<?php

declare(strict_types=1);

namespace App\Middleware;

class EntrepriseMiddleware
{
    public function handle(): ?int
    {
        // TODO: Résoudre l'entreprise active de l'utilisateur connecté
        return $_SESSION['entreprise_id'] ?? null;
    }
}
