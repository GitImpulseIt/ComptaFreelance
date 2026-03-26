<?php

declare(strict_types=1);

namespace App\Middleware;

class EntrepriseMiddleware
{
    public function getEntrepriseId(): ?int
    {
        return $_SESSION['entreprise_id'] ?? null;
    }
}
