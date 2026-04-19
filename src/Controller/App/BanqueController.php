<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\CompteBancaireRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\ImportBancaireRepository;
use App\Repository\LienDocumentRepository;
use App\Repository\LigneComptableRepository;
use App\Repository\PlanComptableRepository;
use App\Repository\PlanComptableSimplifieRepository;
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
    private LienDocumentRepository $lienRepo;
    private PlanComptableRepository $planRepo;
    private PlanComptableSimplifieRepository $planSimpRepo;
    private EntrepriseRepository $entrepriseRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->transactionRepo = new TransactionBancaireRepository($pdo);
        $this->compteRepo = new CompteBancaireRepository($pdo);
        $this->ligneRepo = new LigneComptableRepository($pdo);
        $this->lienRepo = new LienDocumentRepository($pdo);
        $this->planRepo = new PlanComptableRepository($pdo);
        $this->planSimpRepo = new PlanComptableSimplifieRepository($pdo);
        $this->entrepriseRepo = new EntrepriseRepository($pdo);
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
            'import_skipped' => isset($_GET['import_skipped']) ? (int) $_GET['import_skipped'] : null,
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

            $result = $importService->importerFichier($compteId, $tmpPath, $format);

            header('Location: /app/banque?import_success=' . $result['inserted'] . '&import_skipped=' . $result['skipped']);
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

        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $planMode = $entreprise['plan_comptable'] ?? 'simplifie';
        $planComptable = $planMode === 'general'
            ? $this->planRepo->findSelectable()
            : $this->planSimpRepo->findAll();

        echo $this->twig->render('app/banque/show.html.twig', [
            'active_page' => 'banque',
            'transaction' => $transaction,
            'lignes' => $lignes,
            'liens' => $liens,
            'plan_comptable' => $planComptable,
            'plan_mode' => $planMode,
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
        $ttc = isset($_GET['ttc']);

        // Récupérer toutes les lignes comptables individuelles
        $stmt = $this->pdo->prepare(
            "SELECT lc.compte, lc.montant_ht, lc.tva, lc.type
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
             ORDER BY lc.compte, t.date, t.id"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $dateDebut,
            'fin' => $dateFin,
        ]);
        $lignes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Copier le template dans un fichier temporaire
        $templatePath = dirname(__DIR__, 3) . '/storage/templates/balance-teledec.xlsx';
        $tmpFile = tempnam(sys_get_temp_dir(), 'teledec_') . '.xlsx';
        copy($templatePath, $tmpFile);

        $zip = new \ZipArchive();
        $zip->open($tmpFile);

        // Construire le sharedStrings.xml avec les en-têtes d'origine
        $sharedStrings = [
            'Numéro de compte',
            'Débit',
            'Crédit',
            'Solde débiteur',
            'Solde créditeur',
            'Intitulé de compte (peut rester vide)',
        ];
        // Ajouter les numéros de compte comme shared strings
        $compteIndexes = [];
        foreach ($lignes as $l) {
            if (!isset($compteIndexes[$l['compte']])) {
                $compteIndexes[$l['compte']] = count($sharedStrings);
                $sharedStrings[] = $l['compte'];
            }
        }
        $ssCount = count($sharedStrings);
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $ssCount . '" uniqueCount="' . $ssCount . '">';
        foreach ($sharedStrings as $s) {
            $ssXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $ssXml .= '</sst>';

        // Construire le sheet1.xml
        $rowCount = count($lignes) + 1;
        $lastRow = $rowCount;
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $sheetXml .= '<dimension ref="A1:F' . $lastRow . '"/>';
        $sheetXml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>';
        $sheetXml .= '<sheetFormatPr baseColWidth="10" defaultRowHeight="15"/>';
        $sheetXml .= '<cols>';
        $sheetXml .= '<col min="1" max="1" width="22.6640625" style="16" customWidth="1"/>';
        $sheetXml .= '<col min="2" max="2" width="37.5" style="1" customWidth="1"/>';
        $sheetXml .= '<col min="3" max="6" width="20" style="5" customWidth="1"/>';
        $sheetXml .= '</cols>';
        $sheetXml .= '<sheetData>';

        // Ligne d'en-tête (row 1) - indices shared strings : 0=NumCompte, 5=Intitulé, 1=Débit, 2=Crédit, 3=SoldeDébiteur, 4=SoldeCréditeur
        $sheetXml .= '<row r="1" s="4" customFormat="1" ht="18">';
        $sheetXml .= '<c r="A1" s="15" t="s"><v>0</v></c>';
        $sheetXml .= '<c r="B1" s="2" t="s"><v>5</v></c>';
        $sheetXml .= '<c r="C1" s="2" t="s"><v>1</v></c>';
        $sheetXml .= '<c r="D1" s="2" t="s"><v>2</v></c>';
        $sheetXml .= '<c r="E1" s="2" t="s"><v>3</v></c>';
        $sheetXml .= '<c r="F1" s="2" t="s"><v>4</v></c>';
        $sheetXml .= '</row>';

        // Lignes de données
        $r = 2;
        foreach ($lignes as $l) {
            $montant = $ttc ? (float) $l['montant_ht'] + (float) $l['tva'] : (float) $l['montant_ht'];
            $debit = $l['type'] === 'DBT' ? $montant : 0;
            $credit = $l['type'] === 'CRD' ? $montant : 0;
            $ssIndex = $compteIndexes[$l['compte']];

            $sheetXml .= '<row r="' . $r . '">';
            $sheetXml .= '<c r="A' . $r . '" s="16" t="s"><v>' . $ssIndex . '</v></c>';
            $sheetXml .= '<c r="B' . $r . '" s="1"/>';
            $sheetXml .= '<c r="C' . $r . '" s="5"><v>' . $debit . '</v></c>';
            $sheetXml .= '<c r="D' . $r . '" s="5"><v>' . $credit . '</v></c>';
            $sheetXml .= '<c r="E' . $r . '" s="5"><f>IF(C' . $r . '&gt;D' . $r . ',C' . $r . '-D' . $r . ',"")</f></c>';
            $sheetXml .= '<c r="F' . $r . '" s="5"><f>IF(D' . $r . '&gt;C' . $r . ',D' . $r . '-C' . $r . ',"")</f></c>';
            $sheetXml .= '</row>';
            $r++;
        }

        $sheetXml .= '</sheetData>';
        $sheetXml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $sheetXml .= '</worksheet>';

        // Remplacer les fichiers dans le ZIP
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);
        // Supprimer calcChain (Excel le recalcule à l'ouverture)
        $zip->deleteName('xl/calcChain.xml');

        $zip->close();

        $filename = 'balance-teledec_' . $dateDebut . '_' . $dateFin . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    }

    public function rapprocher(int $id): void {}
}
