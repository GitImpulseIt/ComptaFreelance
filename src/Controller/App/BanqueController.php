<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\CompteBancaireRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\ImportBancaireRepository;
use App\Repository\LienDocumentRepository;
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
    private LienDocumentRepository $lienRepo;
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

        $entreprise = $this->entrepriseRepo->findById($entrepriseId);

        echo $this->twig->render('app/banque/index.html.twig', [
            'active_page' => 'banque',
            'transactions' => $transactions,
            'stats' => $stats,
            'comptes' => $comptes,
            'filtres' => $filtres,
            'import_success' => isset($_GET['import_success']) ? (int) $_GET['import_success'] : null,
            'entreprise' => $entreprise,
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

    public function exportFec(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);

        // Vérifier que l'entreprise est à l'IR/BNC
        if (empty($entreprise['option_ir']) || ($entreprise['regime_benefices'] ?? 'BNC') !== 'BNC') {
            header('Location: /app/banque');
            exit;
        }

        $dateDebut = $_GET['date_debut'] ?? date('Y') . '-01-01';
        $dateFin = $_GET['date_fin'] ?? date('Y-m-d');

        // Récupérer les transactions qualifiées avec leurs lignes comptables
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.date, t.libelle, t.montant, t.type,
                    cb.nom AS compte_bancaire
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND EXISTS (SELECT 1 FROM lignes_comptables lc WHERE lc.transaction_bancaire_id = t.id)
             ORDER BY t.date, t.id"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $dateDebut,
            'fin' => $dateFin,
        ]);
        $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Pré-charger toutes les lignes comptables
        $transactionIds = array_column($transactions, 'id');
        $lignesParTransaction = [];
        if (!empty($transactionIds)) {
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT * FROM lignes_comptables WHERE transaction_bancaire_id IN ($placeholders) ORDER BY transaction_bancaire_id, id"
            );
            $stmt->execute($transactionIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $ligne) {
                $lignesParTransaction[(int) $ligne['transaction_bancaire_id']][] = $ligne;
            }
        }

        // Nommage du fichier : {SIREN}FEC{AAAAMMJJ}
        $siren = substr(str_replace(' ', '', $entreprise['siret'] ?? ''), 0, 9);
        $dateClotureFormatted = str_replace('-', '', $dateFin);
        $filename = $siren . 'FEC' . $dateClotureFormatted . '.txt';

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');

        // En-tête (22 champs BNC trésorerie, séparés par tabulation)
        $headers = [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
            'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib',
            'PieceRef', 'PieceDate', 'EcritureLib',
            'Debit', 'Credit', 'EcritureLet', 'DateLet', 'ValidDate',
            'Montantdevise', 'Idevise',
            'DateRglt', 'ModeRglt', 'NatOp', 'IdClient',
        ];
        fwrite($out, implode("\t", $headers) . "\r\n");

        $ecritureNum = 0;

        foreach ($transactions as $tx) {
            $ecritureNum++;
            $numFormatted = str_pad((string) $ecritureNum, 5, '0', STR_PAD_LEFT);
            $dateCompta = str_replace('-', '', $tx['date']);
            $lignes = $lignesParTransaction[(int) $tx['id']] ?? [];

            // Séparer la ligne banque (512xxx) des lignes de contrepartie
            $ligneBanque = null;
            $lignesContrepartie = [];
            foreach ($lignes as $l) {
                if (str_starts_with($l['compte'], '512')) {
                    $ligneBanque = $l;
                } else {
                    $lignesContrepartie[] = $l;
                }
            }

            // Si pas de contrepartie, toutes les lignes sont des contreparties
            // (la ligne banque sera générée depuis la transaction)
            if ($ligneBanque === null && empty($lignesContrepartie)) {
                continue;
            }
            if (empty($lignesContrepartie)) {
                $lignesContrepartie = $lignes;
                $ligneBanque = null;
            }

            // Déterminer le sens comptable de la ligne banque
            // credit (money in) → Debit 512000 / debit (money out) → Credit 512000
            $montantBanque = (float) $tx['montant'];
            $banqueDebit = $tx['type'] === 'credit' ? $this->formatMontantFec($montantBanque) : '0,00';
            $banqueCredit = $tx['type'] === 'debit' ? $this->formatMontantFec($montantBanque) : '0,00';

            $pieceRef = 'BQ' . $numFormatted;

            // Ligne banque (512000)
            $compteBanque = $ligneBanque ? $ligneBanque['compte'] : '512000';
            $this->writeFecLine($out, [
                'BQ', 'Banque', $numFormatted, $dateCompta,
                $compteBanque, $this->libelleCompte($compteBanque), '', '',
                $pieceRef, $dateCompta, $tx['libelle'],
                $banqueDebit, $banqueCredit, '', '', $dateCompta,
                '', '',
                $dateCompta, 'VIR', '', '',
            ]);

            // Lignes de contrepartie
            foreach ($lignesContrepartie as $lc) {
                $montantHt = (float) $lc['montant_ht'];
                $tva = (float) $lc['tva'];
                $compte = $lc['compte'];

                // Le sens des contreparties : inverse du sens banque
                $cpDebit = $tx['type'] === 'debit' ? $this->formatMontantFec($montantHt) : '0,00';
                $cpCredit = $tx['type'] === 'credit' ? $this->formatMontantFec($montantHt) : '0,00';

                $this->writeFecLine($out, [
                    'BQ', 'Banque', $numFormatted, $dateCompta,
                    $compte, $this->libelleCompte($compte), '', '',
                    $pieceRef, $dateCompta, $tx['libelle'],
                    $cpDebit, $cpCredit, '', '', $dateCompta,
                    '', '',
                    $dateCompta, 'VIR', '', '',
                ]);

                // Ligne TVA si montant > 0
                if ($tva > 0.001) {
                    $compteTva = $tx['type'] === 'debit' ? '445660' : '445710';
                    $tvaDebit = $tx['type'] === 'debit' ? $this->formatMontantFec($tva) : '0,00';
                    $tvaCredit = $tx['type'] === 'credit' ? $this->formatMontantFec($tva) : '0,00';

                    $this->writeFecLine($out, [
                        'BQ', 'Banque', $numFormatted, $dateCompta,
                        $compteTva, $this->libelleCompte($compteTva), '', '',
                        $pieceRef, $dateCompta, $tx['libelle'],
                        $tvaDebit, $tvaCredit, '', '', $dateCompta,
                        '', '',
                        $dateCompta, 'VIR', '', '',
                    ]);
                }
            }
        }

        fclose($out);
        exit;
    }

    private function writeFecLine($out, array $fields): void
    {
        fwrite($out, implode("\t", $fields) . "\r\n");
    }

    private function formatMontantFec(float $montant): string
    {
        return number_format($montant, 2, ',', '');
    }

    private function libelleCompte(string $compte): string
    {
        $pcg = [
            '101000' => 'Capital',
            '108000' => 'Compte de l\'exploitant',
            '120000' => 'Resultat de l\'exercice (benefice)',
            '129000' => 'Resultat de l\'exercice (perte)',
            '164000' => 'Emprunts',
            '206000' => 'Droit au bail',
            '211000' => 'Terrains',
            '213000' => 'Constructions',
            '215000' => 'Installations techniques',
            '218000' => 'Autres immobilisations corporelles',
            '218200' => 'Materiel de transport',
            '218300' => 'Materiel de bureau et informatique',
            '218400' => 'Mobilier',
            '271000' => 'Titres immobilises',
            '275000' => 'Depots et cautionnements',
            '280000' => 'Amortissements des immobilisations',
            '281300' => 'Amort. constructions',
            '281500' => 'Amort. installations techniques',
            '281800' => 'Amort. autres immobilisations corporelles',
            '401000' => 'Fournisseurs',
            '411000' => 'Clients',
            '421000' => 'Personnel - Remunerations dues',
            '431000' => 'Securite sociale',
            '437000' => 'Autres organismes sociaux',
            '445510' => 'TVA a decaisser',
            '445660' => 'TVA deductible sur ABS',
            '445670' => 'Credit de TVA',
            '445710' => 'TVA collectee',
            '455000' => 'Associes - Comptes courants',
            '512000' => 'Banque',
            '530000' => 'Caisse',
            '580000' => 'Virements internes',
            '601000' => 'Achats de matieres premieres',
            '602000' => 'Achats stockes',
            '604000' => 'Achats d\'etudes et prestations',
            '606100' => 'Fournitures non stockables',
            '606300' => 'Fournitures d\'entretien',
            '606400' => 'Fournitures administratives',
            '611000' => 'Sous-traitance generale',
            '613000' => 'Locations',
            '613200' => 'Locations immobilieres',
            '614000' => 'Charges locatives',
            '615000' => 'Entretien et reparations',
            '616000' => 'Primes d\'assurances',
            '618000' => 'Divers',
            '622000' => 'Remunerations d\'intermediaires',
            '622600' => 'Honoraires',
            '623000' => 'Publicite, publications',
            '625000' => 'Deplacements, missions, receptions',
            '625100' => 'Voyages et deplacements',
            '625600' => 'Missions',
            '625700' => 'Receptions',
            '626000' => 'Frais postaux et telecommunications',
            '627000' => 'Services bancaires',
            '628000' => 'Divers',
            '631000' => 'Impots, taxes sur remunerations',
            '635000' => 'Autres impots et taxes',
            '635100' => 'CFE',
            '641000' => 'Remunerations du personnel',
            '645000' => 'Charges de securite sociale',
            '646000' => 'Cotisations sociales personnelles',
            '651000' => 'Redevances',
            '661000' => 'Charges d\'interets',
            '668000' => 'Autres charges financieres',
            '671000' => 'Charges exceptionnelles',
            '681000' => 'Dotations aux amortissements',
            '695000' => 'Impot sur les benefices',
            '701000' => 'Ventes de produits finis',
            '706000' => 'Prestations de services',
            '707000' => 'Ventes de marchandises',
            '708000' => 'Produits des activites annexes',
            '709000' => 'RRR accordes par l\'entreprise',
            '761000' => 'Produits de participations',
            '764000' => 'Revenus de valeurs mobilieres',
            '768000' => 'Autres produits financiers',
            '771000' => 'Produits exceptionnels',
            '775000' => 'Produits des cessions d\'elements d\'actif',
            '781000' => 'Reprises sur amortissements',
            '791000' => 'Transferts de charges',
        ];

        // Correspondance exacte
        if (isset($pcg[$compte])) {
            return $pcg[$compte];
        }

        // Correspondance par préfixe (du plus long au plus court)
        for ($len = strlen($compte) - 1; $len >= 3; $len--) {
            $prefix = substr($compte, 0, $len) . str_repeat('0', strlen($compte) - $len);
            if (isset($pcg[$prefix])) {
                return $pcg[$prefix];
            }
        }

        // Classe générique
        $classes = [
            '1' => 'Comptes de capitaux',
            '2' => 'Comptes d\'immobilisations',
            '3' => 'Comptes de stocks',
            '4' => 'Comptes de tiers',
            '5' => 'Comptes financiers',
            '6' => 'Comptes de charges',
            '7' => 'Comptes de produits',
        ];

        return $classes[substr($compte, 0, 1)] ?? 'Compte ' . $compte;
    }

    public function rapprocher(int $id): void {}
}
