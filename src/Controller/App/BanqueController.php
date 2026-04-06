<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\CompteBancaireRepository;
use App\Repository\ImportBancaireRepository;
use App\Repository\LienDocumentRepository;
use App\Repository\LigneComptableRepository;
use App\Repository\TransactionBancaireRepository;
use App\Service\Banque\ImportService;
use App\Service\Banque\ParserFactory;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Twig\Environment;

class BanqueController
{
    private TransactionBancaireRepository $transactionRepo;
    private CompteBancaireRepository $compteRepo;
    private LigneComptableRepository $ligneRepo;
    private LienDocumentRepository $lienRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->transactionRepo = new TransactionBancaireRepository($pdo);
        $this->compteRepo = new CompteBancaireRepository($pdo);
        $this->ligneRepo = new LigneComptableRepository($pdo);
        $this->lienRepo = new LienDocumentRepository($pdo);
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

    public function exportCsv(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $dateDebut = $_GET['date_debut'] ?? date('Y') . '-01-01';
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $stmt = $this->pdo->prepare(
            "SELECT t.date, t.libelle, t.montant, t.type,
                    cb.nom AS compte_bancaire
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
             ORDER BY t.date, t.id"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $dateDebut,
            'fin' => $dateFin,
        ]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $filename = 'releve-bancaire_' . $dateDebut . '_' . $dateFin . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Libellé', 'Montant', 'Type', 'Compte bancaire'], ';');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['date'],
                $row['libelle'],
                $row['type'] === 'debit' ? '-' . $row['montant'] : $row['montant'],
                $row['type'],
                $row['compte_bancaire'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function exportCsvQualifie(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $dateDebut = $_GET['date_debut'] ?? date('Y') . '-01-01';
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        $stmt = $this->pdo->prepare(
            "SELECT t.date, t.libelle, t.type,
                    cb.nom AS compte_bancaire,
                    lc.compte AS code_comptable, lc.montant_ht, lc.tva, lc.type AS sens
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             LEFT JOIN lignes_comptables lc ON lc.transaction_bancaire_id = t.id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
             ORDER BY t.date, t.id, lc.id"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $dateDebut,
            'fin' => $dateFin,
        ]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $filename = 'operations-qualifiees_' . $dateDebut . '_' . $dateFin . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Libellé', 'Type', 'Compte bancaire', 'Code comptable', 'Montant HT', 'TVA', 'Sens'], ';');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['date'],
                $row['libelle'],
                $row['type'],
                $row['compte_bancaire'],
                $row['code_comptable'] ?? '',
                $row['montant_ht'] ?? '',
                $row['tva'] ?? '',
                $row['sens'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
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

        $liens = $this->lienRepo->findByTransaction($id);
        $comptesUtilises = $this->ligneRepo->findDistinctComptes();

        echo $this->twig->render('app/banque/show.html.twig', [
            'active_page' => 'banque',
            'transaction' => $transaction,
            'lignes' => $lignes,
            'liens' => $liens,
            'comptes_utilises' => $comptesUtilises,
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

    public function addLien(int $id): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $transaction = $this->transactionRepo->findById($id);
        $compte = $transaction ? $this->compteRepo->findById((int) $transaction['compte_bancaire_id']) : null;

        if (!$transaction || !$compte || (int) $compte['entreprise_id'] !== $entrepriseId) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorisé']);
            return;
        }

        $url = trim($_POST['url'] ?? '');
        if ($url === '') {
            http_response_code(400);
            echo json_encode(['error' => 'URL vide']);
            return;
        }

        $lienId = $this->lienRepo->create($id, $url);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => $lienId]);
    }

    public function deleteLien(int $id): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $transaction = $this->transactionRepo->findById($id);
        $compte = $transaction ? $this->compteRepo->findById((int) $transaction['compte_bancaire_id']) : null;

        if (!$transaction || !$compte || (int) $compte['entreprise_id'] !== $entrepriseId) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorisé']);
            return;
        }

        $lienId = (int) ($_POST['lien_id'] ?? 0);
        $lien = $this->lienRepo->findById($lienId);

        if ($lien && (int) $lien['transaction_bancaire_id'] === $id) {
            $this->lienRepo->delete($lienId);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function exportTeledec(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $dateDebut = $_GET['date_debut'] ?? date('Y') . '-01-01';
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        // Agréger les montants par compte comptable sur la période
        $stmt = $this->pdo->prepare(
            "SELECT lc.compte,
                    COALESCE(SUM(CASE WHEN lc.type = 'DBT' THEN lc.montant_ht + lc.tva ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN lc.type = 'CRD' THEN lc.montant_ht + lc.tva ELSE 0 END), 0) AS total_credit
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
             GROUP BY lc.compte
             ORDER BY lc.compte"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $dateDebut,
            'fin' => $dateFin,
        ]);
        $balances = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Balance');

        // En-tête
        $sheet->setCellValue('A1', 'Numéro de compte');
        $sheet->setCellValue('B1', 'Intitulé de compte (peut rester vide)');
        $sheet->setCellValue('C1', 'Débit');
        $sheet->setCellValue('D1', 'Crédit');
        $sheet->setCellValue('E1', 'Solde débiteur');
        $sheet->setCellValue('F1', 'Solde créditeur');

        // Format texte pour la colonne A (numéros de compte)
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');

        // Format monétaire pour C, D, E, F
        $euroFormat = '_-* #,##0.00\ "€"_-;\-* #,##0.00\ "€"_-;_-* "-"??\ "€"_-;_-@_-';

        $row = 2;
        foreach ($balances as $b) {
            $debit = (float) $b['total_debit'];
            $credit = (float) $b['total_credit'];

            $sheet->setCellValueExplicit('A' . $row, $b['compte'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, '');
            $sheet->setCellValue('C' . $row, $debit);
            $sheet->setCellValue('D' . $row, $credit);
            $sheet->setCellValue('E' . $row, "=IF(C{$row}>D{$row},C{$row}-D{$row},\"\")");
            $sheet->setCellValue('F' . $row, "=IF(D{$row}>C{$row},D{$row}-C{$row},\"\")");

            $sheet->getStyle("C{$row}:F{$row}")->getNumberFormat()->setFormatCode($euroFormat);

            $row++;
        }

        $filename = 'balance-teledec_' . $dateDebut . '_' . $dateFin . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function rapprocher(int $id): void {}
}
