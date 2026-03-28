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

            // Calculer TVA collectée et déductible pour cette période
            $tvaCalc = $this->calculerTVA($entrepriseId, $annee, $ech['periode']);
            $ech['tva_collectee'] = $tvaCalc['collectee'];
            $ech['tva_deductible'] = $tvaCalc['deductible'];
            $ech['tva_due'] = $tvaCalc['collectee'] - $tvaCalc['deductible'];

            // Pour la régularisation : soustraire les acomptes déjà payés
            if ($ech['type'] === 'regularisation') {
                $ech['tva_due_brute'] = $ech['tva_due'];
                $ech['acomptes_payes'] = $totalPayeAcomptes;
                $ech['tva_due'] = $ech['tva_due'] - $totalPayeAcomptes;
            }

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

            // Pré-remplir case_01 avec la TVA calculée
            if (!$ech['declaration']) {
                $ech['case_01_auto'] = max(0, round($ech['tva_due'], 2));
            }
        }
        unset($ech);

        echo $this->twig->render('app/tva/index.html.twig', [
            'active_page' => 'tva',
            'annee' => $annee,
            'annees' => $annees,
            'echeances' => $echeances,
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

        $case01 = (float) str_replace(',', '.', $_POST['case_01'] ?? '0');
        $case02 = (float) str_replace(',', '.', $_POST['case_02'] ?? '0');
        $case03 = max(0, $case01 - $case02);
        $case05 = (float) str_replace(',', '.', $_POST['case_05'] ?? '0');
        $case06 = (float) str_replace(',', '.', $_POST['case_06'] ?? '0');
        $case07 = max(0, $case06 - $case05);
        $case08 = (float) str_replace(',', '.', $_POST['case_08'] ?? '0');

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

    private function calculerTVA(int $entrepriseId, int $annee, string $periode): array
    {
        // Déterminer les bornes de dates
        if ($periode === 'S1') {
            $dateDebut = $annee . '-01-01';
            $dateFin = $annee . '-06-30';
        } elseif ($periode === 'S2') {
            $dateDebut = $annee . '-07-01';
            $dateFin = $annee . '-12-31';
        } else {
            // ANNUEL
            $dateDebut = $annee . '-01-01';
            $dateFin = $annee . '-12-31';
        }

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

        $collectee = 0;
        $deductible = 0;

        foreach ($stmt->fetchAll() as $l) {
            $tva = abs((float) $l['tva']);
            $compte = $l['compte'];

            if (str_starts_with($compte, '70')) {
                $collectee += $tva;
            } else {
                $deductible += $tva;
            }
        }

        return ['collectee' => round($collectee, 2), 'deductible' => round($deductible, 2)];
    }
}
