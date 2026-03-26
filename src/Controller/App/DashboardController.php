<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use PDO;
use Twig\Environment;

class DashboardController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {}

    public function index(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $stats = [
            'ca' => $this->pdo->prepare('SELECT COALESCE(SUM(montant_ttc), 0) FROM factures WHERE entreprise_id = :id AND statut = \'payee\''),
            'depenses' => $this->pdo->prepare('SELECT COALESCE(SUM(montant_ttc), 0) FROM depenses WHERE entreprise_id = :id'),
            'factures_impayees' => $this->pdo->prepare('SELECT COUNT(*) FROM factures WHERE entreprise_id = :id AND statut = \'envoyee\''),
        ];

        foreach ($stats as $stmt) {
            $stmt->execute(['id' => $entrepriseId]);
        }

        $ca = (float) $stats['ca']->fetchColumn();
        $depenses = (float) $stats['depenses']->fetchColumn();

        echo $this->twig->render('app/dashboard/index.html.twig', [
            'stats' => [
                'ca' => $ca,
                'depenses' => $depenses,
                'factures_impayees' => (int) $stats['factures_impayees']->fetchColumn(),
                'resultat' => $ca - $depenses,
            ],
        ]);
    }
}
