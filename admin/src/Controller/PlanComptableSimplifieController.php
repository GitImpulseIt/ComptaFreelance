<?php

declare(strict_types=1);

namespace Admin\Controller;

use PDO;
use Twig\Environment;

class PlanComptableSimplifieController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
    ) {}

    public function index(): void
    {
        $categorie = trim((string) ($_GET['categorie'] ?? ''));
        $sens = $_GET['sens'] ?? '';
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "SELECT numero, libelle, categorie, mots_cles, sens, pcg_parent FROM plan_comptable_simplifie WHERE 1=1";
        $params = [];

        if ($categorie !== '') {
            $sql .= " AND categorie = :categorie";
            $params['categorie'] = $categorie;
        }
        if (in_array($sens, ['D', 'C'], true)) {
            $sql .= " AND sens = :sens";
            $params['sens'] = $sens;
        }
        if ($q !== '') {
            $sql .= " AND (numero LIKE :prefix OR LOWER(libelle) LIKE :needle OR LOWER(mots_cles) LIKE :needle)";
            $params['prefix'] = $q . '%';
            $params['needle'] = '%' . mb_strtolower($q) . '%';
        }

        $sql .= " ORDER BY numero";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $comptes = $stmt->fetchAll();

        $categories = $this->pdo->query(
            "SELECT categorie, COUNT(*) AS total FROM plan_comptable_simplifie GROUP BY categorie ORDER BY categorie"
        )->fetchAll();

        echo $this->twig->render('plan-comptable-simplifie/index.html.twig', [
            'active_page' => 'plan-comptable-simplifie',
            'comptes' => $comptes,
            'categories' => $categories,
            'categorie_active' => $categorie,
            'sens_active' => $sens,
            'q' => $q,
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function create(): void
    {
        $categories = $this->pdo->query(
            "SELECT DISTINCT categorie FROM plan_comptable_simplifie ORDER BY categorie"
        )->fetchAll(PDO::FETCH_COLUMN);

        echo $this->twig->render('plan-comptable-simplifie/create.html.twig', [
            'active_page' => 'plan-comptable-simplifie',
            'categories' => $categories,
        ]);
    }

    public function store(): void
    {
        $data = $this->sanitize($_POST);
        $error = $this->validate($data);

        if ($error) {
            $_SESSION['error'] = $error;
            header('Location: /plan-comptable-simplifie/create');
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM plan_comptable_simplifie WHERE numero = :n");
        $stmt->execute(['n' => $data['numero']]);
        if ($stmt->fetchColumn()) {
            $_SESSION['error'] = "Le compte {$data['numero']} existe déjà.";
            header('Location: /plan-comptable-simplifie/create');
            exit;
        }

        $this->pdo->prepare(
            "INSERT INTO plan_comptable_simplifie (numero, libelle, categorie, mots_cles, exemples, sens, pcg_parent)
             VALUES (:numero, :libelle, :categorie, :mots_cles, :exemples, :sens, :pcg_parent)"
        )->execute($data);

        header('Location: /plan-comptable-simplifie?success=created');
        exit;
    }

    public function edit(int $numero): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM plan_comptable_simplifie WHERE numero = :n");
        $stmt->execute(['n' => (string) $numero]);
        $compte = $stmt->fetch();

        if (!$compte) {
            header('Location: /plan-comptable-simplifie');
            exit;
        }

        $categories = $this->pdo->query(
            "SELECT DISTINCT categorie FROM plan_comptable_simplifie ORDER BY categorie"
        )->fetchAll(PDO::FETCH_COLUMN);

        echo $this->twig->render('plan-comptable-simplifie/edit.html.twig', [
            'active_page' => 'plan-comptable-simplifie',
            'compte' => $compte,
            'categories' => $categories,
        ]);
    }

    public function update(int $numero): void
    {
        $data = $this->sanitize($_POST);
        $data['numero'] = (string) $numero;
        $error = $this->validate($data, skipNumero: true);

        if ($error) {
            $_SESSION['error'] = $error;
            header('Location: /plan-comptable-simplifie/' . $numero . '/edit');
            exit;
        }

        $this->pdo->prepare(
            "UPDATE plan_comptable_simplifie SET
                libelle = :libelle, categorie = :categorie, mots_cles = :mots_cles,
                exemples = :exemples, sens = :sens, pcg_parent = :pcg_parent,
                updated_at = now()
             WHERE numero = :numero"
        )->execute($data);

        header('Location: /plan-comptable-simplifie?success=updated');
        exit;
    }

    public function delete(int $numero): void
    {
        $this->pdo->prepare("DELETE FROM plan_comptable_simplifie WHERE numero = :n")
            ->execute(['n' => (string) $numero]);

        header('Location: /plan-comptable-simplifie?success=deleted');
        exit;
    }

    private function sanitize(array $post): array
    {
        return [
            'numero' => trim((string) ($post['numero'] ?? '')),
            'libelle' => trim((string) ($post['libelle'] ?? '')),
            'categorie' => trim((string) ($post['categorie'] ?? '')),
            'mots_cles' => trim((string) ($post['mots_cles'] ?? '')) ?: null,
            'exemples' => trim((string) ($post['exemples'] ?? '')) ?: null,
            'sens' => $post['sens'] ?? '',
            'pcg_parent' => trim((string) ($post['pcg_parent'] ?? '')) ?: null,
        ];
    }

    private function validate(array $data, bool $skipNumero = false): ?string
    {
        if (!$skipNumero && !preg_match('/^\d{2,10}$/', $data['numero'])) {
            return 'Le numéro doit contenir 2 à 10 chiffres.';
        }
        if ($data['libelle'] === '') {
            return 'Le libellé est requis.';
        }
        if ($data['categorie'] === '') {
            return 'La catégorie est requise.';
        }
        if (!in_array($data['sens'], ['D', 'C'], true)) {
            return 'Le sens doit être D (débit) ou C (crédit).';
        }
        return null;
    }
}
