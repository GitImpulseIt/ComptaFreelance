<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use PDO;
use Twig\Environment;

class ImmobilisationController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {}

    public function index(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM immobilisations WHERE entreprise_id = :eid ORDER BY date_acquisition DESC"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        $immobilisations = $stmt->fetchAll();

        // Calculer l'amortissement pour chaque immobilisation
        $today = date('Y-m-d');
        foreach ($immobilisations as &$immo) {
            $immo['amortissement'] = $this->calculerAmortissement($immo, $today);
        }
        unset($immo);

        echo $this->twig->render('app/immobilisations/index.html.twig', [
            'active_page' => 'immobilisations',
            'immobilisations' => $immobilisations,
            'success' => isset($_GET['success']),
        ]);
    }

    public function create(): void
    {
        echo $this->twig->render('app/immobilisations/form.html.twig', [
            'active_page' => 'immobilisations',
            'immo' => null,
        ]);
    }

    public function store(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO immobilisations (entreprise_id, designation, date_acquisition, date_mise_en_service, valeur_acquisition, duree_amortissement, type_amortissement, compte)
             VALUES (:eid, :designation, :date_acq, :date_mes, :valeur, :duree, :type, :compte)"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'designation' => trim($_POST['designation'] ?? ''),
            'date_acq' => $_POST['date_acquisition'] ?? '',
            'date_mes' => $_POST['date_mise_en_service'] ?: null,
            'valeur' => (float) str_replace(',', '.', $_POST['valeur_acquisition'] ?? '0'),
            'duree' => (int) ($_POST['duree_amortissement'] ?? 5),
            'type' => $_POST['type_amortissement'] ?? 'lineaire',
            'compte' => trim($_POST['compte'] ?? '218'),
        ]);

        header('Location: /app/immobilisations?success=1');
        exit;
    }

    public function edit(int $id): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $stmt = $this->pdo->prepare("SELECT * FROM immobilisations WHERE id = :id AND entreprise_id = :eid");
        $stmt->execute(['id' => $id, 'eid' => $entrepriseId]);
        $immo = $stmt->fetch();

        if (!$immo) {
            header('Location: /app/immobilisations');
            exit;
        }

        echo $this->twig->render('app/immobilisations/form.html.twig', [
            'active_page' => 'immobilisations',
            'immo' => $immo,
        ]);
    }

    public function update(int $id): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $stmt = $this->pdo->prepare(
            "UPDATE immobilisations SET designation = :designation, date_acquisition = :date_acq,
                date_mise_en_service = :date_mes, valeur_acquisition = :valeur,
                duree_amortissement = :duree, type_amortissement = :type, compte = :compte,
                cession_date = :cess_date, cession_montant = :cess_montant, updated_at = NOW()
             WHERE id = :id AND entreprise_id = :eid"
        );
        $stmt->execute([
            'id' => $id, 'eid' => $entrepriseId,
            'designation' => trim($_POST['designation'] ?? ''),
            'date_acq' => $_POST['date_acquisition'] ?? '',
            'date_mes' => $_POST['date_mise_en_service'] ?: null,
            'valeur' => (float) str_replace(',', '.', $_POST['valeur_acquisition'] ?? '0'),
            'duree' => (int) ($_POST['duree_amortissement'] ?? 5),
            'type' => $_POST['type_amortissement'] ?? 'lineaire',
            'compte' => trim($_POST['compte'] ?? '218'),
            'cess_date' => $_POST['cession_date'] ?: null,
            'cess_montant' => $_POST['cession_montant'] ? (float) str_replace(',', '.', $_POST['cession_montant']) : null,
        ]);

        header('Location: /app/immobilisations?success=1');
        exit;
    }

    public function delete(int $id): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $stmt = $this->pdo->prepare("DELETE FROM immobilisations WHERE id = :id AND entreprise_id = :eid");
        $stmt->execute(['id' => $id, 'eid' => $entrepriseId]);

        header('Location: /app/immobilisations?success=1');
        exit;
    }

    private function calculerAmortissement(array $immo, string $today): array
    {
        $valeur = (float) $immo['valeur_acquisition'];
        $duree = (int) $immo['duree_amortissement'];
        $dateAcq = $immo['date_mise_en_service'] ?? $immo['date_acquisition'];

        if ($duree <= 0 || $valeur <= 0) {
            return ['annuite' => 0, 'cumul' => 0, 'vnc' => $valeur, 'termine' => false];
        }

        $annuite = round($valeur / $duree, 2);
        $debut = new \DateTime($dateAcq);
        $now = new \DateTime($today);

        // Nombre d'années écoulées (prorata au mois pour la 1ère année)
        $moisDebut = (int) $debut->format('n');
        $anneeDebut = (int) $debut->format('Y');
        $anneeNow = (int) $now->format('Y');

        // Prorata de la première année
        $prorata1 = (12 - $moisDebut + 1) / 12;
        $cumul = 0;

        if ($anneeNow >= $anneeDebut) {
            // Première année (prorata)
            $cumul = round($annuite * $prorata1, 2);

            // Années complètes intermédiaires
            $anneesCompletes = max(0, $anneeNow - $anneeDebut - 1);
            $cumul += $annuite * $anneesCompletes;

            // Année en cours (si différente de la 1ère)
            if ($anneeNow > $anneeDebut) {
                $moisNow = (int) $now->format('n');
                $cumul += round($annuite * $moisNow / 12, 2);
            }
        }

        // Plafonner au montant total
        $cumul = min($cumul, $valeur);
        $vnc = round($valeur - $cumul, 2);
        $termine = $vnc <= 0;

        // Si cédé, l'amortissement s'arrête à la date de cession
        if ($immo['cession_date']) {
            $termine = true;
        }

        return [
            'annuite' => $annuite,
            'cumul' => round($cumul, 2),
            'vnc' => $vnc,
            'termine' => $termine,
        ];
    }
}
