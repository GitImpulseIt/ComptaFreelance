<?php

declare(strict_types=1);

namespace Admin\Controller;

use PDO;
use Twig\Environment;

class TvaCalendrierController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
    ) {}

    public function index(): void
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT annee FROM tva_echeances ORDER BY annee DESC"
        );
        $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $echeancesParAnnee = [];
        foreach ($annees as $annee) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM tva_echeances WHERE annee = :annee ORDER BY date_echeance"
            );
            $stmt->execute(['annee' => $annee]);
            $echeancesParAnnee[$annee] = $stmt->fetchAll();
        }

        echo $this->twig->render('tva/index.html.twig', [
            'active_page' => 'tva',
            'annees' => $annees,
            'echeances_par_annee' => $echeancesParAnnee,
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function edit(int $annee): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tva_echeances WHERE annee = :annee ORDER BY date_echeance"
        );
        $stmt->execute(['annee' => $annee]);
        $echeances = $stmt->fetchAll();

        if (empty($echeances)) {
            header('Location: /tva-calendrier');
            exit;
        }

        echo $this->twig->render('tva/edit.html.twig', [
            'active_page' => 'tva',
            'annee' => $annee,
            'echeances' => $echeances,
        ]);
    }

    public function update(int $annee): void
    {
        $ids = $_POST['id'] ?? [];
        $libelles = $_POST['libelle'] ?? [];
        $types = $_POST['type'] ?? [];
        $periodes = $_POST['periode'] ?? [];
        $dates = $_POST['date_echeance'] ?? [];

        $stmt = $this->pdo->prepare(
            "UPDATE tva_echeances SET libelle = :libelle, type = :type, periode = :periode, date_echeance = :date WHERE id = :id AND annee = :annee"
        );

        for ($i = 0; $i < count($ids); $i++) {
            $stmt->execute([
                'id' => (int) $ids[$i],
                'annee' => $annee,
                'libelle' => trim($libelles[$i] ?? ''),
                'type' => $types[$i] ?? 'acompte',
                'periode' => $periodes[$i] ?? 'S1',
                'date' => $dates[$i] ?? '',
            ]);
        }

        header('Location: /tva-calendrier?success=updated');
        exit;
    }

    public function create(): void
    {
        echo $this->twig->render('tva/create.html.twig', [
            'active_page' => 'tva',
        ]);
    }

    public function store(): void
    {
        $annee = (int) ($_POST['annee'] ?? 0);

        if ($annee < 2020 || $annee > 2050) {
            header('Location: /tva-calendrier/create');
            exit;
        }

        // Vérifier si l'année existe déjà
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tva_echeances WHERE annee = :annee");
        $stmt->execute(['annee' => $annee]);
        if ((int) $stmt->fetchColumn() > 0) {
            header('Location: /tva-calendrier/create');
            exit;
        }

        // Créer les 3 échéances standard du régime simplifié
        $stmt = $this->pdo->prepare(
            "INSERT INTO tva_echeances (annee, code, libelle, type, periode, date_echeance) VALUES (:annee, :code, :libelle, :type, :periode, :date)"
        );

        $stmt->execute(['annee' => $annee, 'code' => $annee . '-S1', 'libelle' => 'Acompte TVA 1er semestre', 'type' => 'acompte', 'periode' => 'S1', 'date' => $annee . '-07-24']);
        $stmt->execute(['annee' => $annee, 'code' => $annee . '-S2', 'libelle' => 'Acompte TVA 2ème semestre', 'type' => 'acompte', 'periode' => 'S2', 'date' => $annee . '-12-24']);
        $stmt->execute(['annee' => $annee, 'code' => $annee . '-REG', 'libelle' => 'Régularisation annuelle', 'type' => 'regularisation', 'periode' => 'ANNUEL', 'date' => ($annee + 1) . '-05-04']);

        header('Location: /tva-calendrier/' . $annee . '/edit?success=created');
        exit;
    }

    public function delete(int $annee): void
    {
        $this->pdo->prepare("DELETE FROM tva_echeances WHERE annee = :annee")->execute(['annee' => $annee]);

        header('Location: /tva-calendrier?success=deleted');
        exit;
    }
}
