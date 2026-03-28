<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\CompteBancaireRepository;
use App\Repository\ImportBancaireRepository;
use App\Repository\TransactionBancaireRepository;
use App\Service\Banque\ImportService;
use App\Service\Banque\ParserFactory;
use PDO;
use Twig\Environment;

class BanqueController
{
    private TransactionBancaireRepository $transactionRepo;
    private CompteBancaireRepository $compteRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->transactionRepo = new TransactionBancaireRepository($pdo);
        $this->compteRepo = new CompteBancaireRepository($pdo);
    }

    public function transactions(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $filtres = [
            'compte_id' => !empty($_GET['compte']) ? (int) $_GET['compte'] : null,
            'type' => !empty($_GET['type']) ? $_GET['type'] : null,
            'statut' => !empty($_GET['statut']) ? $_GET['statut'] : null,
            'date_debut' => !empty($_GET['date_debut']) ? $_GET['date_debut'] : null,
            'date_fin' => !empty($_GET['date_fin']) ? $_GET['date_fin'] : null,
            'recherche' => !empty($_GET['q']) ? $_GET['q'] : null,
        ];

        $transactions = $this->transactionRepo->findAllByEntreprise($entrepriseId, $filtres);
        $stats = $this->transactionRepo->countByEntreprise($entrepriseId);
        $comptes = $this->compteRepo->findAllByEntreprise($entrepriseId);

        echo $this->twig->render('app/banque/index.html.twig', [
            'active_page' => 'banque',
            'transactions' => $transactions,
            'stats' => $stats,
            'comptes' => $comptes,
            'filtres' => $filtres,
            'import_success' => isset($_GET['import_success']) ? (int) $_GET['import_success'] : null,
        ]);
    }

    public function showImport(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $comptes = $this->compteRepo->findAllByEntreprise($entrepriseId);

        echo $this->twig->render('app/banque/import.html.twig', [
            'active_page' => 'banque',
            'comptes' => $comptes,
        ]);
    }

    public function import(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $comptes = $this->compteRepo->findAllByEntreprise($entrepriseId);

        $compteId = (int) ($_POST['compte_id'] ?? 0);
        $format = $_POST['format'] ?? '';

        // Vérifier que le compte appartient à l'entreprise
        $compte = $this->compteRepo->findById($compteId);
        if (!$compte || (int) $compte['entreprise_id'] !== $entrepriseId) {
            echo $this->twig->render('app/banque/import.html.twig', [
                'active_page' => 'banque',
                'comptes' => $comptes,
                'error' => 'Compte bancaire invalide.',
            ]);
            return;
        }

        // Vérifier le fichier uploadé
        if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
            echo $this->twig->render('app/banque/import.html.twig', [
                'active_page' => 'banque',
                'comptes' => $comptes,
                'error' => 'Erreur lors de l\'envoi du fichier.',
            ]);
            return;
        }

        $tmpPath = $_FILES['fichier']['tmp_name'];

        try {
            $importService = new ImportService(
                new ParserFactory(),
                new ImportBancaireRepository($this->pdo),
                $this->transactionRepo,
            );

            $count = $importService->importerFichier($compteId, $tmpPath, $format);

            header('Location: /app/banque?import_success=' . $count);
            exit;
        } catch (\Throwable $e) {
            echo $this->twig->render('app/banque/import.html.twig', [
                'active_page' => 'banque',
                'comptes' => $comptes,
                'error' => 'Erreur lors de l\'import : ' . $e->getMessage(),
            ]);
        }
    }

    public function rapprocher(int $id): void {}
}
