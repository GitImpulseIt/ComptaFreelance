<?php

declare(strict_types=1);

namespace Admin\Controller;

use PDO;
use Twig\Environment;

class DashboardController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
    ) {}

    public function index(): void
    {
        $stats = [
            'nb_entreprises' => $this->pdo->query('SELECT COUNT(*) FROM entreprises')->fetchColumn(),
            'nb_entreprises_actives' => $this->pdo->query('SELECT COUNT(*) FROM entreprises WHERE active = true')->fetchColumn(),
            'nb_users' => $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'nb_comptes_bancaires' => $this->pdo->query('SELECT COUNT(*) FROM comptes_bancaires')->fetchColumn(),
            'nb_imports' => $this->pdo->query('SELECT COUNT(*) FROM imports_bancaires')->fetchColumn(),
        ];

        echo $this->twig->render('dashboard/index.html.twig', ['stats' => $stats]);
    }
}
