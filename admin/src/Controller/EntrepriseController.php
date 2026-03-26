<?php

declare(strict_types=1);

namespace Admin\Controller;

use Admin\Service\EntrepriseService;
use Twig\Environment;

class EntrepriseController
{
    public function __construct(
        private Environment $twig,
        private EntrepriseService $service,
    ) {}

    public function index(): void
    {
        echo $this->twig->render('entreprises/index.html.twig', [
            'entreprises' => $this->service->findAll(),
        ]);
    }

    public function create(): void
    {
        echo $this->twig->render('entreprises/form.html.twig', ['entreprise' => []]);
    }

    public function store(): void
    {
        $this->service->create([
            'raison_sociale' => $_POST['raison_sociale'] ?? '',
            'siret' => $_POST['siret'] ?? '',
            'adresse' => $_POST['adresse'] ?? '',
            'code_postal' => $_POST['code_postal'] ?? '',
            'ville' => $_POST['ville'] ?? '',
            'telephone' => $_POST['telephone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'regime_tva' => $_POST['regime_tva'] ?? 'franchise',
        ]);
        header('Location: /entreprises');
        exit;
    }

    public function show(int $id): void
    {
        $entreprise = $this->service->findById($id);
        if (!$entreprise) { header('Location: /entreprises'); exit; }
        $users = $this->service->findUsers($id);
        echo $this->twig->render('entreprises/show.html.twig', [
            'entreprise' => $entreprise,
            'users' => $users,
        ]);
    }

    public function edit(int $id): void
    {
        $entreprise = $this->service->findById($id);
        if (!$entreprise) { header('Location: /entreprises'); exit; }
        echo $this->twig->render('entreprises/form.html.twig', ['entreprise' => $entreprise]);
    }

    public function update(int $id): void
    {
        $this->service->update($id, [
            'raison_sociale' => $_POST['raison_sociale'] ?? '',
            'siret' => $_POST['siret'] ?? '',
            'adresse' => $_POST['adresse'] ?? '',
            'code_postal' => $_POST['code_postal'] ?? '',
            'ville' => $_POST['ville'] ?? '',
            'telephone' => $_POST['telephone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'regime_tva' => $_POST['regime_tva'] ?? 'franchise',
        ]);
        header('Location: /entreprises/' . $id);
        exit;
    }

    public function suspend(int $id): void
    {
        $this->service->suspend($id);
        header('Location: /entreprises/' . $id);
        exit;
    }
}
