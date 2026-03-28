<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\EntrepriseRepository;
use PDO;
use Twig\Environment;

class DashboardController
{
    private EntrepriseRepository $entrepriseRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->entrepriseRepo = new EntrepriseRepository($pdo);
    }

    public function index(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);

        // Années disponibles
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT EXTRACT(YEAR FROM t.date)::int AS annee
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
             ORDER BY annee DESC"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($annees)) {
            $annees = [(int) date('Y')];
        }

        // Lignes comptables de l'année
        $stmt = $this->pdo->prepare(
            "SELECT l.compte, l.type, l.montant_ht, l.tva
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND EXTRACT(YEAR FROM t.date) = :annee"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);
        $lignes = $stmt->fetchAll();

        $caHt = 0;
        $tvaEntrant = 0;
        $tvaSortant = 0;
        $charges = 0;
        $impots = 0;
        $prelevements = 0;

        foreach ($lignes as $l) {
            $compte = $l['compte'];
            $ht = (float) $l['montant_ht'];
            $tva = (float) $l['tva'];
            $type = $l['type'];

            if (str_starts_with($compte, '70')) {
                $caHt += $ht;
                $tvaEntrant += $tva;
            } elseif (preg_match('/^6[0-5]/', $compte)) {
                $charges += $ht + $tva;
                $tvaSortant += $tva;
            } elseif (preg_match('/^6[6-7]/', $compte)) {
                $impots += $ht + $tva;
            } elseif (str_starts_with($compte, '4550') || str_starts_with($compte, '4551') || $compte === '455000') {
                if ($type === 'DBT') {
                    $prelevements += $ht;
                }
            }
        }

        // Soldes bancaires
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0)
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid AND t.date < :debut"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $annee . '-01-01']);
        $soldeDebut = (float) $stmt->fetchColumn();

        $currentYear = (int) date('Y');
        $dateFin = ($annee < $currentYear) ? ($annee + 1) . '-01-01' : date('Y-m-d', strtotime('+1 day'));
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0)
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid AND t.date < :fin"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $dateFin]);
        $soldeFin = (float) $stmt->fetchColumn();

        $soldeVariation = $soldeFin - $soldeDebut;
        $exerciceTermine = $annee < $currentYear;

        $tvaDiff = $tvaEntrant - $tvaSortant;
        $benefice = $caHt - $charges - $impots;

        $currentMonth = (int) date('n');
        $nbMois = ($annee < $currentYear) ? 12 : (($annee === $currentYear) ? $currentMonth : 1);

        // Déterminer si l'option IR est active pour cette année
        $irActif = false;
        if ($entreprise) {
            $statut = $entreprise['statut_juridique'] ?? '';
            $optionIr = (bool) ($entreprise['option_ir'] ?? false);
            $finExercice = $entreprise['option_ir_fin_exercice'] ?? null;

            if ($statut === 'EI') {
                $irActif = true;
            } elseif (in_array($statut, ['EURL', 'SARL'])) {
                $irActif = $optionIr;
            } elseif (in_array($statut, ['SAS', 'SASU'])) {
                $irActif = $optionIr && ($finExercice === null || $annee <= (int) $finExercice);
            }
        }

        // Calcul IR si actif
        $ir = null;
        if ($irActif && $benefice > 0) {
            $quotient = $this->getQuotientFamilial($entrepriseId, $annee);
            $ir = $this->calculerIR($benefice, $annee, $quotient);
            $ir['quotient'] = $quotient;
            $ir['revenu_apres_ir'] = $benefice - $ir['total'];
            $ir['revenu_mensuel_apres_ir'] = $nbMois > 0 ? ($benefice - $ir['total']) / $nbMois : 0;
            $ir['disponible'] = $benefice - $ir['total'] - $prelevements;
        }

        echo $this->twig->render('app/dashboard/index.html.twig', [
            'active_page' => 'dashboard',
            'annee' => $annee,
            'annees' => $annees,
            'exercice_termine' => $exerciceTermine,
            'ir_actif' => $irActif,
            'ir' => $ir,
            'stats' => [
                'solde_debut' => $soldeDebut,
                'solde_fin' => $soldeFin,
                'solde_variation' => $soldeVariation,
                'ca_ht' => $caHt,
                'tva_entrant' => $tvaEntrant,
                'tva_sortant' => $tvaSortant,
                'tva_diff' => $tvaDiff,
                'charges' => $charges,
                'impots' => $impots,
                'benefice' => $benefice,
                'prelevements' => $prelevements,
                'benefice_mensuel' => $nbMois > 0 ? $benefice / $nbMois : 0,
            ],
        ]);
    }

    public function updateQuotient(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = (int) ($_POST['annee'] ?? date('Y'));
        $quotient = (float) ($_POST['quotient'] ?? 1.0);

        if ($quotient < 0.5) $quotient = 0.5;
        if ($quotient > 10) $quotient = 10.0;

        $stmt = $this->pdo->prepare(
            "INSERT INTO quotients_familiaux (entreprise_id, annee, quotient)
             VALUES (:eid, :annee, :quotient)
             ON CONFLICT (entreprise_id, annee) DO UPDATE SET quotient = :quotient"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee, 'quotient' => $quotient]);

        header('Location: /app?annee=' . $annee);
        exit;
    }

    private function getQuotientFamilial(int $entrepriseId, int $annee): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT quotient FROM quotients_familiaux WHERE entreprise_id = :eid AND annee = :annee"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (float) $result : 1.0;
    }

    private function calculerIR(float $benefice, int $annee, float $quotient): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT tranche_min, tranche_max, taux FROM ir_tranches WHERE annee = :annee ORDER BY tranche_min"
        );
        $stmt->execute(['annee' => $annee]);
        $tranches = $stmt->fetchAll();

        if (empty($tranches)) {
            return ['total' => 0, 'detail' => []];
        }

        $revenuParPart = $benefice / $quotient;
        $ir = 0;
        $restant = $revenuParPart;
        $detail = [];

        foreach ($tranches as $tranche) {
            if ($restant <= 0) break;

            $min = (float) $tranche['tranche_min'];
            $max = $tranche['tranche_max'] !== null ? (float) $tranche['tranche_max'] : PHP_FLOAT_MAX;
            $taux = (float) $tranche['taux'] / 100;

            $montantTrancheMax = $max - $min;
            $montantTranche = min($restant, $montantTrancheMax);

            if ($montantTranche > 0) {
                $irTranche = $montantTranche * $taux;
                $ir += $irTranche;

                $detail[] = [
                    'min' => $min,
                    'max' => $max === PHP_FLOAT_MAX ? null : $max,
                    'taux' => $taux * 100,
                    'base' => $montantTranche,
                    'impot' => $irTranche,
                ];
            }

            $restant -= $montantTranche;
        }

        return [
            'total' => round($ir * $quotient, 2),
            'detail' => $detail,
        ];
    }
}
