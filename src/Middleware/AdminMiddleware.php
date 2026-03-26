<?php

declare(strict_types=1);

namespace App\Middleware;

class AdminMiddleware
{
    public function handle(): bool
    {
        // TODO: Vérifier que l'utilisateur a le rôle admin
        return ($_SESSION['user']['role'] ?? '') === 'admin';
    }
}
