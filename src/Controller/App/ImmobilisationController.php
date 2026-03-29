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
        // Pré-remplissage depuis une transaction bancaire
        $prefill = null;
        if (isset($_GET['designation'])) {
            $prefill = [
                'designation' => $_GET['designation'] ?? '',
                'date_acquisition' => $_GET['date_acquisition'] ?? '',
                'valeur_acquisition' => $_GET['valeur'] ?? '',
            ];
        }

        echo $this->twig->render('app/immobilisations/form.html.twig', [
            'active_page' => 'immobilisations',
            'immo' => null,
            'prefill' => $prefill,
            'retour' => $_GET['retour'] ?? null,
        ]);
    }

    public function store(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO immobilisations (entreprise_id, designation, date_acquisition, date_mise_en_service, valeur_acquisition, duree_amortissement, type_amortissement, coeff_degressif, compte)
             VALUES (:eid, :designation, :date_acq, :date_mes, :valeur, :duree, :type, :coeff, :compte)"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'designation' => trim($_POST['designation'] ?? ''),
            'date_acq' => $_POST['date_acquisition'] ?? '',
            'date_mes' => $_POST['date_mise_en_service'] ?: null,
            'valeur' => (float) str_replace(',', '.', $_POST['valeur_acquisition'] ?? '0'),
            'duree' => (int) ($_POST['duree_amortissement'] ?? 5),
            'type' => $_POST['type_amortissement'] ?? 'lineaire',
            'coeff' => (float) ($_POST['coeff_degressif'] ?? 1.25),
            'compte' => trim($_POST['compte'] ?? '218'),
        ]);

        $retour = $_POST['retour'] ?? null;
        if ($retour) {
            header('Location: /app/banque/' . (int) $retour . '?success=1');
        } else {
            header('Location: /app/immobilisations?success=1');
        }
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
                duree_amortissement = :duree, type_amortissement = :type, coeff_degressif = :coeff,
                compte = :compte, cession_date = :cess_date, cession_montant = :cess_montant, updated_at = NOW()
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
            'coeff' => (float) ($_POST['coeff_degressif'] ?? 1.25),
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
        $type = $immo['type_amortissement'];
        $dateAcq = $immo['date_mise_en_service'] ?? $immo['date_acquisition'];

        if ($duree <= 0 || $valeur <= 0) {
            return ['annuite' => 0, 'cumul' => 0, 'vnc' => $valeur, 'termine' => false];
        }

        $debut = new \DateTime($dateAcq);
        $now = new \DateTime($today);
        $moisDebut = (int) $debut->format('n');
        $anneeDebut = (int) $debut->format('Y');
        $anneeNow = (int) $now->format('Y');

        if ($type === 'degressif') {
            return $this->calculerDegressif($valeur, $duree, (float) ($immo['coeff_degressif'] ?? 1.25), $moisDebut, $anneeDebut, $anneeNow, $immo);
        }

        // Amortissement linéaire
        $annuiteBase = round($valeur / $duree, 2);
        $prorata1 = (12 - $moisDebut + 1) / 12;
        $cumul = 0;
        $annuite = 0;
        $annuites = [];

        for ($a = $anneeDebut; $cumul < $valeur; $a++) {
            $dot = $annuiteBase;
            if ($a === $anneeDebut) {
                $dot = round($annuiteBase * $prorata1, 2);
            }
            $dot = min($dot, round($valeur - $cumul, 2));
            $cumul += $dot;
            $vnc = round($valeur - $cumul, 2);
            $annuite = $dot;
            $annuites[] = ['annee' => $a, 'dotation' => $dot, 'cumul' => round($cumul, 2), 'vnc' => max(0, $vnc)];

            if ($a >= $anneeNow) {
                break;
            }
        }

        $cumul = min($cumul, $valeur);
        $vnc = round($valeur - $cumul, 2);
        $termine = $vnc <= 0;

        if ($immo['cession_date']) {
            $termine = true;
        }

        // Compléter les annuités futures (plafonné à durée + 1 pour le prorata)
        $anneeFin = $anneeDebut + $duree;
        if (!$termine) {
            $tmpCumul = $cumul;
            for ($af = $a + 1; $af <= $anneeFin && $tmpCumul < $valeur; $af++) {
                $dot = min($annuiteBase, round($valeur - $tmpCumul, 2));
                $tmpCumul += $dot;
                $annuites[] = ['annee' => $af, 'dotation' => $dot, 'cumul' => round($tmpCumul, 2), 'vnc' => max(0, round($valeur - $tmpCumul, 2))];
            }
        }

        return ['annuite' => $annuite, 'cumul' => round($cumul, 2), 'vnc' => max(0, $vnc), 'termine' => $termine, 'annuites' => $annuites];
    }

    private function calculerDegressif(float $valeur, int $duree, float $coeff, int $moisDebut, int $anneeDebut, int $anneeNow, array $immo): array
    {
        $tauxDegressif = (1 / $duree) * $coeff;

        $anneeFin = $anneeDebut + $duree - 1;
        $vnc = $valeur;
        $cumul = 0;
        $annuite = 0;
        $annuites = [];
        $dureeRestante = $duree;

        for ($a = $anneeDebut; $a <= $anneeFin && $vnc > 0; $a++) {
            // Annuité dégressive vs linéaire sur durée restante : on prend le max
            $tauxLinRestant = $dureeRestante > 0 ? 1 / $dureeRestante : 1;
            $taux = max($tauxDegressif, $tauxLinRestant);
            $dotation = round($vnc * $taux, 2);

            if ($a === $anneeDebut) {
                $prorata = (12 - $moisDebut + 1) / 12;
                $dotation = round($vnc * $tauxDegressif * $prorata, 2);
            }

            $dotation = min($dotation, $vnc);
            $cumul += $dotation;
            $vnc = round($valeur - $cumul, 2);
            $annuites[] = ['annee' => $a, 'dotation' => $dotation, 'cumul' => round($cumul, 2), 'vnc' => max(0, $vnc)];
            $dureeRestante--;

            if ($a <= $anneeNow) {
                $annuite = $dotation;
            }
        }

        $cumulNow = 0;
        $vncNow = $valeur;
        foreach ($annuites as $row) {
            if ($row['annee'] <= $anneeNow) {
                $cumulNow = $row['cumul'];
                $vncNow = $row['vnc'];
            }
        }

        $termine = $vncNow <= 0;
        if ($immo['cession_date']) {
            $termine = true;
        }

        return ['annuite' => $annuite, 'cumul' => round($cumulNow, 2), 'vnc' => max(0, $vncNow), 'termine' => $termine, 'annuites' => $annuites];
    }

}
