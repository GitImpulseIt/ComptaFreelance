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
        ['slug' => '2035',        'label' => '2035'],
        ['slug' => '2035-suite',  'label' => '2035-SUITE'],
        ['slug' => '2035-a-p1',   'label' => '2035-A (p.1)'],
        ['slug' => '2035-a-p2',   'label' => '2035-A (p.2)'],
        ['slug' => '2035-b',      'label' => '2035-B'],
        ['slug' => '2035-e',      'label' => '2035-E'],
        ['slug' => '2035-g',      'label' => '2035-G'],
        ['slug' => '2035-rci',    'label' => '2035-RCI'],
        ['slug' => '2049',        'label' => '2049'],
        ['slug' => 'annexe-libre','label' => 'Annexe libre'],
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

    public function tab2035(): void
    {
        $this->renderTab('2035', 'data_2035');
    }

    public function tab2035Suite(): void
    {
        $this->renderTab('2035-suite', 'data_2035_suite');
    }

    public function tab2035AP1(): void
    {
        $this->renderTab('2035-a-p1', 'data_2035_a_p1');
    }

    public function tab2035AP2(): void
    {
        $this->renderTab('2035-a-p2', 'data_2035_a_p2');
    }

    public function tab2035B(): void
    {
        $this->renderTab('2035-b', 'data_2035_b');
    }

    public function tab2035E(): void
    {
        $this->renderTab('2035-e', 'data_2035_e');
    }

    public function tab2035G(): void
    {
        $this->renderTab('2035-g', 'data_2035_g');
    }

    public function tabRCI(): void
    {
        $this->renderTab('2035-rci', 'data_2035_rci');
    }

    public function tab2049(): void
    {
        $this->renderTab('2049', 'data_2049');
    }

    public function tabAnnexeLibre(): void
    {
        $this->renderTab('annexe-libre', 'data_annexe_libre');
    }

    public function save(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = (int) ($_POST['annee'] ?? date('Y') - 1);
        $tab = $_POST['tab'] ?? '';
        $fields = $_POST['fields'] ?? [];

        $columnMap = [
            '2035' => 'data_2035',
            '2035-suite' => 'data_2035_suite',
            '2035-a-p1' => 'data_2035_a_p1',
            '2035-a-p2' => 'data_2035_a_p2',
            '2035-b' => 'data_2035_b',
            '2035-e' => 'data_2035_e',
            '2035-g' => 'data_2035_g',
            '2035-rci' => 'data_2035_rci',
            '2049' => 'data_2049',
            'annexe-libre' => 'data_annexe_libre',
        ];

        $column = $columnMap[$tab] ?? null;
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

    private function renderTab(string $slug, string $dataColumn): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        // Vérifier BNC
        if (($entreprise['regime_benefices'] ?? '') !== 'BNC') {
            header('Location: /app');
            exit;
        }

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
            $annees = [(int) date('Y') - 1];
        }

        $declaration = $this->getOrCreateDeclaration($entrepriseId, $annee);
        $savedData = json_decode($declaration[$dataColumn] ?? '{}', true) ?: [];

        echo $this->twig->render('app/cloture/' . $slug . '.html.twig', [
            'active_page' => 'cloture',
            'active_tab' => $slug,
            'tabs' => self::TABS,
            'annee' => $annee,
            'annees' => $annees,
            'entreprise_data' => $entreprise,
            'declaration' => $declaration,
            'data' => $savedData,
            'success' => isset($_GET['success']),
        ]);
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
