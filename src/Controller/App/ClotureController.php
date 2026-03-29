<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\EntrepriseRepository;
use PDO;
use Twig\Environment;

class ClotureController
{
    private EntrepriseRepository $entrepriseRepo;

    private const TABS = [
        ['slug' => 'bilan',           'label' => 'Bilan'],
        ['slug' => 'compte-resultat', 'label' => 'Compte de résultat'],
        ['slug' => '2035',            'label' => '2035'],
    ];

    // Mapping slug onglet → colonne JSONB en base
    private const TAB_COLUMN = [
        'bilan'           => 'data_bilan',
        'compte-resultat' => 'data_compte_resultat',
        '2035'            => 'data_2035',
    ];

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->entrepriseRepo = new EntrepriseRepository($pdo);
    }

    public function index(): void
    {
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;
        header('Location: /app/cloture/bilan?annee=' . $annee);
        exit;
    }

    public function tabBilan(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        $raw = $this->computeBilanRaw($entrepriseId, $annee);
        $rawN1 = $this->computeBilanRaw($entrepriseId, $annee - 1);

        // Format computed values for N (brut & amort)
        $computed = [];
        foreach ($raw as $k => $v) {
            $computed[$k] = $v != 0 ? (string)(int)round($v) : '';
        }

        // Compute N-1 net values for ACTIF lines (brut - amort)
        $computedN1 = [];
        $pairs = ['010' => '012', '014' => '016', '028' => '030', '040' => '042'];
        foreach ($pairs as $brut => $amort) {
            $net = round(($rawN1[$brut] ?? 0) - ($rawN1[$amort] ?? 0));
            $computedN1[$brut] = $net != 0 ? (string)(int)$net : '';
        }
        if (($rawN1['084'] ?? 0) != 0) {
            $computedN1['084'] = (string)(int)round($rawN1['084']);
        }

        // Passif N-1 (capital social, emprunts)
        foreach (['120', '156'] as $case) {
            if (($rawN1[$case] ?? 0) != 0) {
                $computedN1[$case] = (string)(int)round($rawN1[$case]);
            }
        }

        $this->renderTab('bilan', [
            'computed' => $computed,
            'computed_n1' => $computedN1,
        ]);
    }
    public function tabCompteResultat(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        $computed = $this->computeCompteResultatRaw($entrepriseId, $annee);
        $computedN1 = $this->computeCompteResultatRaw($entrepriseId, $annee - 1);

        $this->renderTab('compte-resultat', [
            'computed' => $computed,
            'computed_n1' => $computedN1,
        ]);
    }
    public function tab2035(): void            { $this->renderTab('2035'); }

    public function save(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = (int) ($_POST['annee'] ?? date('Y') - 1);
        $tab = $_POST['tab'] ?? '';
        $fields = $_POST['fields'] ?? [];

        $column = self::TAB_COLUMN[$tab] ?? null;
        if (!$column) {
            header('Location: /app/cloture?annee=' . $annee);
            exit;
        }

        $this->getOrCreateDeclaration($entrepriseId, $annee);

        $json = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            "UPDATE declarations_2035 SET {$column} = :data, updated_at = NOW()
             WHERE entreprise_id = :eid AND annee = :annee"
        );
        $stmt->execute(['data' => $json, 'eid' => $entrepriseId, 'annee' => $annee]);

        header('Location: /app/cloture/' . $tab . '?annee=' . $annee . '&success=1');
        exit;
    }

    private function renderTab(string $slug, array $extraData = []): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        if (($entreprise['regime_benefices'] ?? '') !== 'BNC') {
            header('Location: /app');
            exit;
        }

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
            $annees = [(int) date('Y') - 1];
        }

        $dataColumn = self::TAB_COLUMN[$slug];
        $declaration = $this->getOrCreateDeclaration($entrepriseId, $annee);
        $savedData = json_decode($declaration[$dataColumn] ?? '{}', true) ?: [];

        echo $this->twig->render('app/cloture/' . $slug . '.html.twig', array_merge([
            'active_page' => 'cloture',
            'active_tab' => $slug,
            'tabs' => self::TABS,
            'annee' => $annee,
            'annees' => $annees,
            'entreprise_data' => $entreprise,
            'declaration' => $declaration,
            'data' => $savedData,
            'success' => isset($_GET['success']),
        ], $extraData));
    }

    private function computeCompteResultatRaw(int $entrepriseId, int $annee): array
    {
        $result = [];

        // Dotations aux amortissements depuis les immobilisations
        $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);
        $dotation = 0.0;
        foreach ($immos as $immo) {
            $dotation += $immo['dotation'];
        }
        if ($dotation != 0) {
            $result['254'] = (string)(int)round($dotation);
        }

        // Production vendue depuis les lignes comptables
        $stmt = $this->pdo->prepare(
            "SELECT lc.compte, SUM(lc.montant_ht) AS total
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND lc.compte LIKE '70%'
             GROUP BY lc.compte"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $comptes70 = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $biens = 0.0;    // 701-705, 709
        $services = 0.0;  // 706-708
        foreach ($comptes70 as $compte => $montant) {
            $p3 = substr((string)$compte, 0, 3);
            if (in_array($p3, ['706', '707', '708'])) {
                $services += (float)$montant;
            } elseif ($p3 >= '701' && $p3 <= '705' || $p3 === '709') {
                $biens += (float)$montant;
            }
        }

        if ($biens != 0) {
            $result['214'] = (string)(int)round($biens);
        }
        if ($services != 0) {
            $result['218'] = (string)(int)round($services);
        }

        // Autres charges externes (comptes 61 et 62)
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND (lc.compte LIKE '61%' OR lc.compte LIKE '62%')"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $chargesExternes = (float)$stmt->fetchColumn();
        if ($chargesExternes != 0) {
            $result['242'] = (string)(int)round($chargesExternes);
        }

        return $result;
    }

    private function computeBilanRaw(int $entrepriseId, int $annee): array
    {
        $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);

        $fc = ['brut' => 0.0, 'amort' => 0.0]; // Fonds commercial (207)
        $ai = ['brut' => 0.0, 'amort' => 0.0]; // Autres incorporelles (20x sauf 207)
        $co = ['brut' => 0.0, 'amort' => 0.0]; // Corporelles (21x)
        $fi = ['brut' => 0.0, 'amort' => 0.0]; // Financières (26x, 27x)

        foreach ($immos as $immo) {
            $compte = $immo['compte'] ?? '218';
            $p2 = substr($compte, 0, 2);
            $p3 = substr($compte, 0, 3);

            if ($p3 === '207') {
                $g = &$fc;
            } elseif ($p2 === '20') {
                $g = &$ai;
            } elseif (in_array($p2, ['26', '27'])) {
                $g = &$fi;
            } else {
                $g = &$co;
            }

            $g['brut'] += $immo['valeur'];
            $g['amort'] += $immo['cumul_total'];
            unset($g);
        }

        // Disponibilités = solde bancaire au 31/12
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0)
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid AND t.date <= :fin"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $annee . '-12-31']);
        $dispo = (float)$stmt->fetchColumn();

        // Capital social = capital N-1 + augmentations de capital (compte 4561) de l'année N
        $capitalN1 = 0.0;
        try {
            $stmtDecl = $this->pdo->prepare(
                "SELECT data_bilan FROM declarations_2035
                 WHERE entreprise_id = :eid AND annee = :annee"
            );
            $stmtDecl->execute(['eid' => $entrepriseId, 'annee' => $annee - 1]);
            $declN1 = $stmtDecl->fetch();
            if ($declN1 && !empty($declN1['data_bilan'])) {
                $bilanN1 = json_decode($declN1['data_bilan'], true) ?: [];
                $capitalN1 = (float)($bilanN1['120'] ?? 0);
            }
        } catch (\PDOException $e) {
            // Colonne data_bilan absente si migration 0013 non exécutée
        }

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '4561%'
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $augmentationCapital = (float)$stmt->fetchColumn();
        $capitalSocial = $capitalN1 + $augmentationCapital;

        // Emprunts et dettes assimilées (compte 455 au débit)
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '455%'
               AND lc.type = 'DBT'
               AND t.date <= :fin"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $annee . '-12-31']);
        $emprunts = (float)$stmt->fetchColumn();

        return [
            '010' => $fc['brut'],  '012' => $fc['amort'],
            '014' => $ai['brut'],  '016' => $ai['amort'],
            '028' => $co['brut'],  '030' => $co['amort'],
            '040' => $fi['brut'],  '042' => $fi['amort'],
            '084' => $dispo,
            '120' => $capitalSocial,
            '156' => $emprunts,
        ];
    }

    private function getImmobilisationsAvecAmortissement(int $entrepriseId, int $annee): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM immobilisations WHERE entreprise_id = :eid ORDER BY date_acquisition"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        $immos = $stmt->fetchAll();

        $finAnnee = $annee . '-12-31';
        $result = [];

        foreach ($immos as $immo) {
            $dateAcq = $immo['date_mise_en_service'] ?? $immo['date_acquisition'];
            $anneeAcq = (int) (new \DateTime($dateAcq))->format('Y');

            // Ignorer les immos acquises après l'année
            if ($anneeAcq > $annee) {
                continue;
            }

            // Ignorer les immos cédées avant l'année
            if ($immo['cession_date'] && (new \DateTime($immo['cession_date']))->format('Y') < $annee) {
                continue;
            }

            $valeur = (float) $immo['valeur_acquisition'];
            $duree = (int) $immo['duree_amortissement'];
            $moisDebut = (int) (new \DateTime($dateAcq))->format('n');

            // Calcul amortissement cumulé fin N-1 et dotation N
            $annuiteLineaire = $duree > 0 ? round($valeur / $duree, 2) : 0;
            $prorata1 = (12 - $moisDebut + 1) / 12;

            $cumulFinN1 = 0;
            $dotationN = 0;

            if ($immo['type_amortissement'] === 'degressif') {
                $coeff = (float) ($immo['coeff_degressif'] ?? 1.25);
                $tauxDeg = (1 / $duree) * $coeff;
                $vnc = $valeur;
                $dureeRestante = $duree;

                for ($a = $anneeAcq; $a <= $annee && $vnc > 0; $a++) {
                    $tauxLinRestant = $dureeRestante > 0 ? 1 / $dureeRestante : 1;
                    $taux = max($tauxDeg, $tauxLinRestant);
                    $dot = round($vnc * $taux, 2);
                    if ($a === $anneeAcq) {
                        $dot = round($dot * $prorata1, 2);
                    }
                    $dot = min($dot, $vnc);

                    if ($a < $annee) {
                        $cumulFinN1 += $dot;
                    } else {
                        $dotationN = $dot;
                    }
                    $vnc = round($valeur - $cumulFinN1 - ($a >= $annee ? $dotationN : 0), 2);
                    $dureeRestante--;
                }
            } else {
                // Linéaire
                for ($a = $anneeAcq; $a <= $annee; $a++) {
                    $dot = $annuiteLineaire;
                    if ($a === $anneeAcq) {
                        $dot = round($annuiteLineaire * $prorata1, 2);
                    }
                    $restant = $valeur - $cumulFinN1 - ($a >= $annee ? $dotationN : 0);
                    $dot = min($dot, max(0, $valeur - $cumulFinN1));

                    if ($a < $annee) {
                        $cumulFinN1 += $dot;
                    } else {
                        $dotationN = $dot;
                    }
                }
            }

            $cumulFinN1 = min($cumulFinN1, $valeur);
            $dotationN = min($dotationN, $valeur - $cumulFinN1);
            $cumulFinN = $cumulFinN1 + $dotationN;
            $vnc = round($valeur - $cumulFinN, 2);

            $result[] = [
                'designation' => $immo['designation'],
                'date_acquisition' => $immo['date_acquisition'],
                'date_mise_en_service' => $immo['date_mise_en_service'],
                'valeur' => $valeur,
                'duree' => $duree,
                'type' => $immo['type_amortissement'],
                'compte' => $immo['compte'],
                'cumul_anterieur' => round($cumulFinN1, 2),
                'dotation' => round($dotationN, 2),
                'cumul_total' => round($cumulFinN, 2),
                'vnc' => max(0, $vnc),
                'cession_date' => $immo['cession_date'],
                'cession_montant' => $immo['cession_montant'],
            ];
        }

        return $result;
    }

    private function getOrCreateDeclaration(int $entrepriseId, int $annee): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO declarations_2035 (entreprise_id, annee) VALUES (:eid, :annee)
             ON CONFLICT (entreprise_id, annee) DO NOTHING"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);

        $stmt = $this->pdo->prepare(
            "SELECT * FROM declarations_2035 WHERE entreprise_id = :eid AND annee = :annee"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);
        return $stmt->fetch() ?: [];
    }
}
