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
        ['slug' => '2035',       'label' => '2035'],
        ['slug' => '2035-suite', 'label' => '2035-SUITE'],
        ['slug' => '2035-a',     'label' => '2035-A'],
        ['slug' => '2035-b',     'label' => '2035-B'],
        ['slug' => '2035-e',     'label' => '2035-E'],
        ['slug' => '2035-f',     'label' => '2035-F'],
        ['slug' => '2035-g',     'label' => '2035-G'],
        ['slug' => '2035-rci',   'label' => '2035-RCI'],
        ['slug' => '2468',       'label' => '2468'],
        ['slug' => 'annexlib01', 'label' => 'ANNEXE LIBRE'],
    ];

    // Mapping slug onglet → colonne JSONB en base
    private const TAB_COLUMN = [
        '2035'       => 'data_2035',
        '2035-suite' => 'data_2035_suite',
        '2035-a'     => 'data_2035_a_p1',
        '2035-b'     => 'data_2035_b',
        '2035-e'     => 'data_2035_e',
        '2035-f'     => 'data_2035_a_p2',
        '2035-g'     => 'data_2035_g',
        '2035-rci'   => 'data_2035_rci',
        '2468'       => 'data_2049',
        'annexlib01' => 'data_annexe_libre',
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
        header('Location: /app/cloture/2035?annee=' . $annee);
        exit;
    }

    public function tab2035(): void      { $this->renderTab('2035'); }
    public function tab2035Suite(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        // Immobilisations avec amortissement
        $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);

        $this->renderTab('2035-suite', ['immobilisations' => $immos]);
    }
    public function tab2035A(): void      { $this->renderTab('2035-a'); }
    public function tab2035B(): void      { $this->renderTab('2035-b'); }
    public function tab2035E(): void      { $this->renderTab('2035-e'); }
    public function tab2035F(): void      { $this->renderTab('2035-f'); }
    public function tab2035G(): void      { $this->renderTab('2035-g'); }
    public function tabRCI(): void        { $this->renderTab('2035-rci'); }
    public function tab2468(): void       { $this->renderTab('2468'); }
    public function tabAnnexeLibre(): void { $this->renderTab('annexlib01'); }

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
