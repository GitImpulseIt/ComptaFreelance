<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\CompteBancaireRepository;
use App\Repository\EntrepriseRepository;
use PDO;
use Twig\Environment;

class ParametreController
{
    private CompteBancaireRepository $compteRepo;
    private EntrepriseRepository $entrepriseRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->compteRepo = new CompteBancaireRepository($pdo);
        $this->entrepriseRepo = new EntrepriseRepository($pdo);
    }

    public function index(): void
    {
        echo $this->twig->render('app/parametres/index.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'general',
        ]);
    }

    public function update(): void {}

    // --- Entreprise ---

    public function entreprise(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);

        echo $this->twig->render('app/parametres/entreprise.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'entreprise',
            'entreprise' => $entreprise,
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function updateEntreprise(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);

        if (!$entreprise) {
            header('Location: /app/parametres/entreprise');
            exit;
        }

        $raisonSociale = trim($_POST['raison_sociale'] ?? '');
        $siret = trim($_POST['siret'] ?? '');

        if ($raisonSociale === '' || $siret === '') {
            echo $this->twig->render('app/parametres/entreprise.html.twig', [
                'active_page' => 'parametres',
                'active_tab' => 'entreprise',
                'entreprise' => $entreprise,
                'error' => 'La raison sociale et le SIRET sont obligatoires.',
                'old' => $_POST,
            ]);
            return;
        }

        $statutJuridique = $_POST['statut_juridique'] ?? $entreprise['statut_juridique'];
        $optionIr = in_array($statutJuridique, ['SAS', 'SASU']) && isset($_POST['option_ir']);
        $optionIrFin = $optionIr && !empty($_POST['option_ir_fin']) ? $_POST['option_ir_fin'] : null;

        $this->entrepriseRepo->update($entrepriseId, [
            'raison_sociale' => $raisonSociale,
            'siret' => $siret,
            'adresse' => trim($_POST['adresse'] ?? ''),
            'code_postal' => trim($_POST['code_postal'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'regime_tva' => $_POST['regime_tva'] ?? $entreprise['regime_tva'],
            'statut_juridique' => $statutJuridique,
            'option_ir' => $optionIr,
            'option_ir_fin' => $optionIrFin,
        ]);

        // Mettre à jour le nom en session
        $_SESSION['user']['entreprise_nom'] = $raisonSociale;

        header('Location: /app/parametres/entreprise?success=updated');
        exit;
    }

    // --- Comptes bancaires ---

    public function comptesBancaires(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $comptes = $this->compteRepo->findAllByEntreprise($entrepriseId);

        echo $this->twig->render('app/parametres/comptes-bancaires.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'comptes-bancaires',
            'comptes' => $comptes,
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function createCompte(): void
    {
        echo $this->twig->render('app/parametres/compte-form.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'comptes-bancaires',
            'compte' => null,
        ]);
    }

    public function storeCompte(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();

        $nom = trim($_POST['nom'] ?? '');
        $banque = trim($_POST['banque'] ?? '');
        $iban = trim($_POST['iban'] ?? '');

        if ($nom === '' && $banque === '') {
            echo $this->twig->render('app/parametres/compte-form.html.twig', [
                'active_page' => 'parametres',
                'active_tab' => 'comptes-bancaires',
                'compte' => null,
                'error' => 'Le nom ou la banque doit être renseigné.',
                'old' => $_POST,
            ]);
            return;
        }

        if ($nom === '') {
            $nom = $this->generateNomCompte($entrepriseId, $banque);
        }

        $this->compteRepo->create([
            'entreprise_id' => $entrepriseId,
            'nom' => $nom,
            'banque' => $banque,
            'iban' => $iban,
        ]);

        header('Location: /app/parametres/comptes-bancaires?success=created');
        exit;
    }

    public function editCompte(int $id): void
    {
        $compte = $this->compteRepo->findById($id);

        if (!$compte) {
            header('Location: /app/parametres/comptes-bancaires');
            exit;
        }

        echo $this->twig->render('app/parametres/compte-form.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'comptes-bancaires',
            'compte' => $compte,
        ]);
    }

    public function updateCompte(int $id): void
    {
        $compte = $this->compteRepo->findById($id);

        if (!$compte) {
            header('Location: /app/parametres/comptes-bancaires');
            exit;
        }

        $nom = trim($_POST['nom'] ?? '');
        $banque = trim($_POST['banque'] ?? '');
        $iban = trim($_POST['iban'] ?? '');

        if ($nom === '' && $banque === '') {
            echo $this->twig->render('app/parametres/compte-form.html.twig', [
                'active_page' => 'parametres',
                'active_tab' => 'comptes-bancaires',
                'compte' => $compte,
                'error' => 'Le nom ou la banque doit être renseigné.',
                'old' => $_POST,
            ]);
            return;
        }

        if ($nom === '') {
            $nom = $this->generateNomCompte($this->auth->getEntrepriseId(), $banque, $id);
        }

        $this->compteRepo->update($id, [
            'nom' => $nom,
            'banque' => $banque,
            'iban' => $iban,
        ]);

        header('Location: /app/parametres/comptes-bancaires?success=updated');
        exit;
    }

    private function generateNomCompte(int $entrepriseId, string $banque, ?int $excludeId = null): string
    {
        $comptes = $this->compteRepo->findAllByEntreprise($entrepriseId);

        $existingNames = [];
        foreach ($comptes as $c) {
            if ($excludeId !== null && (int) $c['id'] === $excludeId) {
                continue;
            }
            $existingNames[] = $c['nom'];
        }

        if (!in_array($banque, $existingNames, true)) {
            return $banque;
        }

        $i = 2;
        while (in_array($banque . ' ' . $i, $existingNames, true)) {
            $i++;
        }

        return $banque . ' ' . $i;
    }

    public function deleteCompte(int $id): void
    {
        $compte = $this->compteRepo->findById($id);

        if ($compte) {
            $this->compteRepo->delete($id);
        }

        header('Location: /app/parametres/comptes-bancaires?success=deleted');
        exit;
    }
}
