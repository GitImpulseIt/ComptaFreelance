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
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');

        // Années disponibles (depuis les transactions)
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

        // Stats depuis les lignes comptables des transactions de l'année
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
        $tvaEntrant = 0;  // TVA collectée (sur ventes)
        $tvaSortant = 0;  // TVA déductible (sur achats)
        $charges = 0;
        $impots = 0;
        $prelevements = 0;

        foreach ($lignes as $l) {
            $compte = $l['compte'];
            $ht = (float) $l['montant_ht'];
            $tva = (float) $l['tva'];
            $type = $l['type'];

            // Comptes 70xxxx = Produits / CA
            if (str_starts_with($compte, '70')) {
                $caHt += $ht;
                $tvaEntrant += $tva;
            }
            // Comptes 60-62 = Charges avec TVA déductible
            elseif (preg_match('/^6[0-2]/', $compte)) {
                $charges += $ht + $tva;
                $tvaSortant += $tva;
            }
            // Comptes 63-65 = Autres charges
            elseif (preg_match('/^6[3-5]/', $compte)) {
                $charges += $ht + $tva;
                $tvaSortant += $tva;
            }
            // Comptes 66-67 = Impôts et taxes
            elseif (preg_match('/^6[6-7]/', $compte)) {
                $impots += $ht + $tva;
            }
            // Compte 455000 = Prélèvements associé
            elseif (str_starts_with($compte, '4550') || str_starts_with($compte, '4551') || $compte === '455000') {
                if ($type === 'DBT') {
                    $prelevements += $ht;
                }
            }
        }

        // Solde bancaire en début d'exercice (avant le 1er janvier de l'année)
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0)
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid AND t.date < :debut"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $annee . '-01-01']);
        $soldeDebut = (float) $stmt->fetchColumn();

        // Solde bancaire en fin d'exercice (ou à date si exercice en cours)
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

        // Nombre de mois écoulés dans l'année sélectionnée
        $currentMonth = (int) date('n');
        $nbMois = ($annee < $currentYear) ? 12 : (($annee === $currentYear) ? $currentMonth : 1);

        echo $this->twig->render('app/dashboard/index.html.twig', [
            'active_page' => 'dashboard',
            'annee' => $annee,
            'annees' => $annees,
            'exercice_termine' => $exerciceTermine,
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
}
