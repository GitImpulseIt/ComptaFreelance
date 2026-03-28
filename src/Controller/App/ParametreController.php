<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\CompteBancaireRepository;
use PDO;
use Twig\Environment;

class ParametreController
{
    private CompteBancaireRepository $compteRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->compteRepo = new CompteBancaireRepository($pdo);
    }

    public function index(): void
    {
        echo $this->twig->render('app/parametres/index.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'general',
        ]);
    }

    public function update(): void {}

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

        if ($nom === '') {
            echo $this->twig->render('app/parametres/compte-form.html.twig', [
                'active_page' => 'parametres',
                'active_tab' => 'comptes-bancaires',
                'compte' => null,
                'error' => 'Le nom du compte est obligatoire.',
                'old' => $_POST,
            ]);
            return;
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

        if ($nom === '') {
            echo $this->twig->render('app/parametres/compte-form.html.twig', [
                'active_page' => 'parametres',
                'active_tab' => 'comptes-bancaires',
                'compte' => $compte,
                'error' => 'Le nom du compte est obligatoire.',
                'old' => $_POST,
            ]);
            return;
        }

        $this->compteRepo->update($id, [
            'nom' => $nom,
            'banque' => $banque,
            'iban' => $iban,
        ]);

        header('Location: /app/parametres/comptes-bancaires?success=updated');
        exit;
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
