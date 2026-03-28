<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use PDO;
use Twig\Environment;

class TvaController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {}

    public function index(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');

        // Années disponibles
        $stmt = $this->pdo->query("SELECT DISTINCT annee FROM tva_echeances ORDER BY annee DESC");
        $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($annees)) {
            $annees = [(int) date('Y')];
        }

        // Échéances de l'année
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tva_echeances WHERE annee = :annee ORDER BY date_echeance"
        );
        $stmt->execute(['annee' => $annee]);
        $echeances = $stmt->fetchAll();

        // Déclarations existantes
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tva_declarations WHERE entreprise_id = :eid AND echeance_id = ANY(SELECT id FROM tva_echeances WHERE annee = :annee)"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);
        $declarations = [];
        foreach ($stmt->fetchAll() as $d) {
            $declarations[$d['echeance_id']] = $d;
        }

        // Calcul TVA par période depuis les lignes comptables
        $totalPayeAcomptes = 0;

        foreach ($echeances as &$ech) {
            $ech['declaration'] = $declarations[$ech['id']] ?? null;

            // Calculer TVA avec détail des cases pour le formulaire 3514
            $tvaCalc = $this->calculerTVA($entrepriseId, $annee, $ech['periode']);
            $ech['tva_collectee'] = $tvaCalc['total_tva_collectee'];
            $ech['tva_deductible'] = $tvaCalc['total_tva_deductible'];
            $ech['tva_due'] = $tvaCalc['tva_due'];

            // Détail des opérations TVA
            $ech['detail_lignes_tva'] = $this->getDetailLignesTVA($entrepriseId, $annee, $ech['periode']);

            // Pour la régularisation : soustraire les acomptes déjà payés
            // On arrondit la TVA brute à l'euro le plus proche avant de soustraire
            // les acomptes (déjà entiers via floor), pour éviter un résidu artificiel
            if ($ech['type'] === 'regularisation') {
                $ech['tva_due_brute'] = $ech['tva_due'];
                $ech['acomptes_payes'] = $totalPayeAcomptes;
                $ech['tva_due'] = (float) ((int) round($ech['tva_due']) - (int) $totalPayeAcomptes);
            }

            // Pré-remplir les cases du formulaire 3514
            $tvaDue = $ech['tva_due'];
            $isRegul = $ech['type'] === 'regularisation';
            $ech['cases_auto'] = [
                'case_01' => $tvaDue > 0 ? ($isRegul ? (int) round($tvaDue) : (int) floor($tvaDue)) : 0,
                'case_02' => 0,
                'case_03' => 0,
                'case_05' => $tvaDue < 0 ? (int) round($tvaCalc['total_tva_collectee']) : 0,
                'case_06' => $tvaDue < 0 ? (int) round($tvaCalc['total_tva_deductible']) : 0,
                'case_07' => $tvaDue < 0 ? (int) round(abs($tvaDue)) : 0,
                'case_08' => 0,
            ];

            // Statut calculé
            $today = date('Y-m-d');
            if ($ech['declaration'] && $ech['declaration']['statut'] === 'payee') {
                $ech['statut_display'] = 'payee';
                if ($ech['type'] === 'acompte') {
                    $totalPayeAcomptes += (float) $ech['declaration']['montant_paye'];
                }
            } elseif ($ech['date_echeance'] < $today) {
                $ech['statut_display'] = 'en_retard';
            } else {
                $ech['statut_display'] = 'a_payer';
            }
        }
        unset($ech);

        echo $this->twig->render('app/tva/index.html.twig', [
            'active_page' => 'tva',
            'annee' => $annee,
            'annees' => $annees,
            'echeances' => $echeances,
            'success' => isset($_GET['success']),
        ]);
    }

    public function payer(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $echeanceId = (int) ($_POST['echeance_id'] ?? 0);
        $annee = (int) ($_POST['annee'] ?? date('Y'));

        // Vérifier que l'échéance existe
        $stmt = $this->pdo->prepare("SELECT * FROM tva_echeances WHERE id = :id");
        $stmt->execute(['id' => $echeanceId]);
        $echeance = $stmt->fetch();

        if (!$echeance) {
            header('Location: /app/tva?annee=' . $annee);
            exit;
        }

        $case01 = (int) ($_POST['case_01'] ?? 0);
        $case02 = (int) ($_POST['case_02'] ?? 0);
        $case03 = max(0, $case01 - $case02);
        $case05 = (int) ($_POST['case_05'] ?? 0);
        $case06 = (int) ($_POST['case_06'] ?? 0);
        $case07 = max(0, $case06 - $case05);
        $case08 = (int) ($_POST['case_08'] ?? 0);

        $montantPaye = $case03 > 0 ? $case03 : 0;

        $stmt = $this->pdo->prepare(
            "INSERT INTO tva_declarations (entreprise_id, echeance_id, case_01, case_02, case_03, case_05, case_06, case_07, case_08, montant_paye, statut, date_paiement)
             VALUES (:eid, :ecid, :c01, :c02, :c03, :c05, :c06, :c07, :c08, :mp, 'payee', CURRENT_DATE)
             ON CONFLICT (entreprise_id, echeance_id) DO UPDATE SET
                case_01 = :c01, case_02 = :c02, case_03 = :c03, case_05 = :c05, case_06 = :c06,
                case_07 = :c07, case_08 = :c08, montant_paye = :mp, statut = 'payee',
                date_paiement = CURRENT_DATE, updated_at = NOW()"
        );
        $stmt->execute([
            'eid' => $entrepriseId, 'ecid' => $echeanceId,
            'c01' => $case01, 'c02' => $case02, 'c03' => $case03,
            'c05' => $case05, 'c06' => $case06, 'c07' => $case07, 'c08' => $case08,
            'mp' => $montantPaye,
        ]);

        header('Location: /app/tva?annee=' . $annee . '&success=1');
        exit;
    }

    public function updateDatePaiement(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $echeanceId = (int) ($_POST['echeance_id'] ?? 0);
        $annee = (int) ($_POST['annee'] ?? date('Y'));
        $datePaiement = $_POST['date_paiement'] ?? '';

        if ($datePaiement && $echeanceId) {
            $stmt = $this->pdo->prepare(
                "UPDATE tva_declarations SET date_paiement = :dp, updated_at = NOW()
                 WHERE entreprise_id = :eid AND echeance_id = :ecid"
            );
            $stmt->execute(['dp' => $datePaiement, 'eid' => $entrepriseId, 'ecid' => $echeanceId]);
        }

        header('Location: /app/tva?annee=' . $annee);
        exit;
    }

    private function calculerTVA(int $entrepriseId, int $annee, string $periode): array
    {
        [$dateDebut, $dateFin] = $this->bornesPeriode($annee, $periode);

        $stmt = $this->pdo->prepare(
            "SELECT l.compte, l.type, l.montant_ht, l.tva
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND l.tva != 0"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $dateDebut, 'fin' => $dateFin]);

        $collectee = 0.0;
        $deductible = 0.0;

        foreach ($stmt->fetchAll() as $l) {
            $tva = abs((float) $l['tva']);
            if (str_starts_with($l['compte'], '70')) {
                $collectee += $tva;
            } else {
                $deductible += $tva;
            }
        }

        $totalCollectee = round($collectee, 2);
        $totalDeductible = round($deductible, 2);

        return [
            'total_tva_collectee' => $totalCollectee,
            'total_tva_deductible' => $totalDeductible,
            'tva_due' => $totalCollectee - $totalDeductible,
        ];
    }

    private function getDetailLignesTVA(int $entrepriseId, int $annee, string $periode): array
    {
        [$dateDebut, $dateFin] = $this->bornesPeriode($annee, $periode);

        $stmt = $this->pdo->prepare(
            "SELECT t.date, t.libelle, l.compte, l.montant_ht, l.tva, l.type
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND l.tva != 0
             ORDER BY t.date, t.id"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $dateDebut, 'fin' => $dateFin]);

        $lignes = [];
        foreach ($stmt->fetchAll() as $l) {
            $lignes[] = [
                'date' => $l['date'],
                'libelle' => $l['libelle'],
                'compte' => $l['compte'],
                'montant_ht' => (float) $l['montant_ht'],
                'tva' => abs((float) $l['tva']),
                'sens_tva' => str_starts_with($l['compte'], '70') ? 'collectee' : 'deductible',
            ];
        }

        return $lignes;
    }

    private function bornesPeriode(int $annee, string $periode): array
    {
        if ($periode === 'S1') {
            return [$annee . '-01-01', $annee . '-06-30'];
        }
        if ($periode === 'S2') {
            return [$annee . '-07-01', $annee . '-12-31'];
        }
        return [$annee . '-01-01', $annee . '-12-31'];
    }
}
