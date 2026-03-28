<?php

declare(strict_types=1);

namespace Admin\Controller;

use PDO;
use Twig\Environment;

class IrController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
    ) {}

    public function index(): void
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT annee FROM ir_tranches ORDER BY annee DESC"
        );
        $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $tranchesParAnnee = [];
        foreach ($annees as $annee) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM ir_tranches WHERE annee = :annee ORDER BY tranche_min"
            );
            $stmt->execute(['annee' => $annee]);
            $tranchesParAnnee[$annee] = $stmt->fetchAll();
        }

        echo $this->twig->render('ir/index.html.twig', [
            'active_page' => 'ir',
            'annees' => $annees,
            'tranches_par_annee' => $tranchesParAnnee,
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function create(): void
    {
        echo $this->twig->render('ir/create.html.twig', [
            'active_page' => 'ir',
        ]);
    }

    public function store(): void
    {
        $annee = (int) ($_POST['annee'] ?? 0);

        if ($annee < 2020 || $annee > 2050) {
            $_SESSION['error'] = 'Année invalide.';
            header('Location: /ir/create');
            exit;
        }

        // Vérifier si l'année existe déjà
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ir_tranches WHERE annee = :annee");
        $stmt->execute(['annee' => $annee]);
        if ((int) $stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Le barème {$annee} existe déjà.";
            header('Location: /ir/create');
            exit;
        }

        $mins = $_POST['tranche_min'] ?? [];
        $maxs = $_POST['tranche_max'] ?? [];
        $taux = $_POST['taux'] ?? [];

        $stmt = $this->pdo->prepare(
            "INSERT INTO ir_tranches (annee, tranche_min, tranche_max, taux)
             VALUES (:annee, :min, :max, :taux)"
        );

        for ($i = 0; $i < count($taux); $i++) {
            $min = (float) str_replace([' ', ','], ['', '.'], $mins[$i] ?? '0');
            $max = trim($maxs[$i] ?? '');
            $t = (float) str_replace(',', '.', $taux[$i] ?? '0');

            $stmt->execute([
                'annee' => $annee,
                'min' => $min,
                'max' => $max === '' ? null : (float) str_replace([' ', ','], ['', '.'], $max),
                'taux' => $t,
            ]);
        }

        header('Location: /ir?success=created');
        exit;
    }

    public function edit(int $annee): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM ir_tranches WHERE annee = :annee ORDER BY tranche_min"
        );
        $stmt->execute(['annee' => $annee]);
        $tranches = $stmt->fetchAll();

        if (empty($tranches)) {
            header('Location: /ir');
            exit;
        }

        echo $this->twig->render('ir/edit.html.twig', [
            'active_page' => 'ir',
            'annee' => $annee,
            'tranches' => $tranches,
        ]);
    }

    public function update(int $annee): void
    {
        // Supprimer les tranches existantes et les recréer
        $this->pdo->prepare("DELETE FROM ir_tranches WHERE annee = :annee")->execute(['annee' => $annee]);

        $mins = $_POST['tranche_min'] ?? [];
        $maxs = $_POST['tranche_max'] ?? [];
        $taux = $_POST['taux'] ?? [];

        $stmt = $this->pdo->prepare(
            "INSERT INTO ir_tranches (annee, tranche_min, tranche_max, taux)
             VALUES (:annee, :min, :max, :taux)"
        );

        for ($i = 0; $i < count($taux); $i++) {
            $min = (float) str_replace([' ', ','], ['', '.'], $mins[$i] ?? '0');
            $max = trim($maxs[$i] ?? '');
            $t = (float) str_replace(',', '.', $taux[$i] ?? '0');

            $stmt->execute([
                'annee' => $annee,
                'min' => $min,
                'max' => $max === '' ? null : (float) str_replace([' ', ','], ['', '.'], $max),
                'taux' => $t,
            ]);
        }

        header('Location: /ir?success=updated');
        exit;
    }

    public function delete(int $annee): void
    {
        $this->pdo->prepare("DELETE FROM ir_tranches WHERE annee = :annee")->execute(['annee' => $annee]);

        header('Location: /ir?success=deleted');
        exit;
    }

    public function duplicate(int $annee): void
    {
        $newAnnee = $annee + 1;

        // Vérifier que la nouvelle année n'existe pas
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ir_tranches WHERE annee = :annee");
        $stmt->execute(['annee' => $newAnnee]);
        if ((int) $stmt->fetchColumn() > 0) {
            header('Location: /ir?success=exists');
            exit;
        }

        $this->pdo->prepare(
            "INSERT INTO ir_tranches (annee, tranche_min, tranche_max, taux)
             SELECT :new_annee, tranche_min, tranche_max, taux FROM ir_tranches WHERE annee = :annee ORDER BY tranche_min"
        )->execute(['new_annee' => $newAnnee, 'annee' => $annee]);

        header('Location: /ir/' . $newAnnee . '/edit?success=duplicated');
        exit;
    }
}
