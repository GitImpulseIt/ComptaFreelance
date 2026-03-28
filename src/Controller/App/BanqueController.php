<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\CompteBancaireRepository;
use App\Repository\ImportBancaireRepository;
use App\Repository\LigneComptableRepository;
use App\Repository\TransactionBancaireRepository;
use App\Service\Banque\ImportService;
use App\Service\Banque\ParserFactory;
use PDO;
use Twig\Environment;

class BanqueController
{
    private TransactionBancaireRepository $transactionRepo;
    private CompteBancaireRepository $compteRepo;
    private LigneComptableRepository $ligneRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->transactionRepo = new TransactionBancaireRepository($pdo);
        $this->compteRepo = new CompteBancaireRepository($pdo);
        $this->ligneRepo = new LigneComptableRepository($pdo);
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

    public function show(int $id): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $transaction = $this->transactionRepo->findById($id);

        if (!$transaction) {
            header('Location: /app/banque');
            exit;
        }

        // Vérifier que la transaction appartient à l'entreprise
        $compte = $this->compteRepo->findById((int) $transaction['compte_bancaire_id']);
        if (!$compte || (int) $compte['entreprise_id'] !== $entrepriseId) {
            header('Location: /app/banque');
            exit;
        }

        $lignes = $this->ligneRepo->findByTransaction($id);

        // Si aucune ligne, pré-remplir avec la ligne principale (compte 512000)
        if (empty($lignes)) {
            $lignes = [[
                'compte' => '512000',
                'montant_ht' => $transaction['montant'],
                'type' => $transaction['type'] === 'debit' ? 'DBT' : 'CRD',
                'tva' => '0',
                'is_main' => true,
            ]];
        } else {
            // Marquer la première ligne comme principale
            $lignes[0]['is_main'] = true;
            for ($i = 1; $i < count($lignes); $i++) {
                $lignes[$i]['is_main'] = false;
            }
        }

        echo $this->twig->render('app/banque/show.html.twig', [
            'active_page' => 'banque',
            'transaction' => $transaction,
            'lignes' => $lignes,
            'success' => isset($_GET['success']),
        ]);
    }

    public function qualify(int $id): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $transaction = $this->transactionRepo->findById($id);

        if (!$transaction) {
            header('Location: /app/banque');
            exit;
        }

        $compte = $this->compteRepo->findById((int) $transaction['compte_bancaire_id']);
        if (!$compte || (int) $compte['entreprise_id'] !== $entrepriseId) {
            header('Location: /app/banque');
            exit;
        }

        $comptes = $_POST['compte'] ?? [];
        $montantsHt = $_POST['montant_ht'] ?? [];
        $types = $_POST['type'] ?? [];
        $tvas = $_POST['tva'] ?? [];

        $lignes = [];
        for ($i = 0; $i < count($comptes); $i++) {
            $lignes[] = [
                'compte' => trim($comptes[$i]),
                'montant_ht' => (float) str_replace(',', '.', $montantsHt[$i] ?? '0'),
                'type' => $types[$i] ?? 'DBT',
                'tva' => (float) str_replace(',', '.', $tvas[$i] ?? '0'),
            ];
        }

        $this->ligneRepo->replaceForTransaction($id, $lignes);

        header('Location: /app/banque/' . $id . '?success=1');
        exit;
    }

    public function rapprocher(int $id): void {}
}
