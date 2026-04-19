<?php

declare(strict_types=1);

namespace Admin\Controller;

use PDO;
use Twig\Environment;

class PlanComptableController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
    ) {}

    public function index(): void
    {
        $classe = isset($_GET['classe']) && $_GET['classe'] !== ''
            ? max(1, min(9, (int) $_GET['classe']))
            : 1;
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "SELECT numero, libelle, classe, optionnel FROM plan_comptable WHERE classe = :classe";
        $params = ['classe' => $classe];

        if ($q !== '') {
            $sql .= " AND (numero LIKE :prefix OR LOWER(libelle) LIKE :needle)";
            $params['prefix'] = $q . '%';
            $params['needle'] = '%' . mb_strtolower($q) . '%';
        }

        $sql .= " ORDER BY numero";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $comptes = $stmt->fetchAll();

        $stats = $this->pdo->query(
            "SELECT classe, COUNT(*) AS total, COUNT(*) FILTER (WHERE optionnel) AS optionnels
             FROM plan_comptable GROUP BY classe ORDER BY classe"
        )->fetchAll();

        echo $this->twig->render('plan-comptable/index.html.twig', [
            'active_page' => 'plan-comptable',
            'comptes' => $comptes,
            'stats' => $stats,
            'classe_active' => $classe,
            'q' => $q,
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function create(): void
    {
        echo $this->twig->render('plan-comptable/create.html.twig', [
            'active_page' => 'plan-comptable',
            'classe_preselect' => (int) ($_GET['classe'] ?? 1),
        ]);
    }

    public function store(): void
    {
        $numero = trim((string) ($_POST['numero'] ?? ''));
        $libelle = trim((string) ($_POST['libelle'] ?? ''));
        $classe = (int) ($_POST['classe'] ?? 0);
        $optionnel = !empty($_POST['optionnel']);

        if (!preg_match('/^\d{2,10}$/', $numero) || $libelle === '' || $classe < 1 || $classe > 9) {
            $_SESSION['error'] = 'Numéro (2 à 10 chiffres), libellé et classe (1-9) sont requis.';
            header('Location: /plan-comptable/create');
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM plan_comptable WHERE numero = :n");
        $stmt->execute(['n' => $numero]);
        if ($stmt->fetchColumn()) {
            $_SESSION['error'] = "Le compte {$numero} existe déjà.";
            header('Location: /plan-comptable/create');
            exit;
        }

        $this->pdo->prepare(
            "INSERT INTO plan_comptable (numero, libelle, classe, optionnel) VALUES (:n, :l, :c, :o)"
        )->execute([
            'n' => $numero,
            'l' => $libelle,
            'c' => $classe,
            'o' => $optionnel ? 't' : 'f',
        ]);

        header('Location: /plan-comptable?classe=' . $classe . '&success=created');
        exit;
    }

    public function edit(int $numero): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM plan_comptable WHERE numero = :n");
        $stmt->execute(['n' => (string) $numero]);
        $compte = $stmt->fetch();

        if (!$compte) {
            header('Location: /plan-comptable');
            exit;
        }

        echo $this->twig->render('plan-comptable/edit.html.twig', [
            'active_page' => 'plan-comptable',
            'compte' => $compte,
        ]);
    }

    public function update(int $numero): void
    {
        $libelle = trim((string) ($_POST['libelle'] ?? ''));
        $classe = (int) ($_POST['classe'] ?? 0);
        $optionnel = !empty($_POST['optionnel']);

        if ($libelle === '' || $classe < 1 || $classe > 9) {
            $_SESSION['error'] = 'Libellé et classe (1-9) sont requis.';
            header('Location: /plan-comptable/' . $numero . '/edit');
            exit;
        }

        $this->pdo->prepare(
            "UPDATE plan_comptable SET libelle = :l, classe = :c, optionnel = :o, updated_at = now() WHERE numero = :n"
        )->execute([
            'n' => (string) $numero,
            'l' => $libelle,
            'c' => $classe,
            'o' => $optionnel ? 't' : 'f',
        ]);

        header('Location: /plan-comptable?classe=' . $classe . '&success=updated');
        exit;
    }

    public function delete(int $numero): void
    {
        $stmt = $this->pdo->prepare("SELECT classe FROM plan_comptable WHERE numero = :n");
        $stmt->execute(['n' => (string) $numero]);
        $classe = (int) ($stmt->fetchColumn() ?: 1);

        $this->pdo->prepare("DELETE FROM plan_comptable WHERE numero = :n")
            ->execute(['n' => (string) $numero]);

        header('Location: /plan-comptable?classe=' . $classe . '&success=deleted');
        exit;
    }
}
