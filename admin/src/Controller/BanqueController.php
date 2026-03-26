<?php

declare(strict_types=1);

namespace Admin\Controller;

use Admin\Service\BanqueService;
use Twig\Environment;

class BanqueController
{
    public function __construct(
        private Environment $twig,
        private BanqueService $service,
    ) {}

    public function index(): void
    {
        echo $this->twig->render('banque/index.html.twig', [
            'comptes' => $this->service->findAllComptes(),
        ]);
    }

    public function show(int $id): void
    {
        $compte = $this->service->findCompteById($id);
        if (!$compte) { header('Location: /banque'); exit; }
        $imports = $this->service->findImportsByCompte($id);
        echo $this->twig->render('banque/show.html.twig', [
            'compte' => $compte,
            'imports' => $imports,
        ]);
    }
}
