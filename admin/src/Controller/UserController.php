<?php

declare(strict_types=1);

namespace Admin\Controller;

use Admin\Service\UserService;
use Twig\Environment;

class UserController
{
    public function __construct(
        private Environment $twig,
        private UserService $service,
    ) {}

    public function index(): void
    {
        echo $this->twig->render('users/index.html.twig', [
            'users' => $this->service->findAll(),
        ]);
    }

    public function show(int $id): void
    {
        $user = $this->service->findById($id);
        if (!$user) { header('Location: /users'); exit; }
        echo $this->twig->render('users/show.html.twig', ['user' => $user]);
    }

    public function resetPassword(int $id): void
    {
        $user = $this->service->findById($id);
        if (!$user) { header('Location: /users'); exit; }

        $newPassword = $this->service->resetPassword($id);

        echo $this->twig->render('users/password-reset.html.twig', [
            'user' => $user,
            'new_password' => $newPassword,
        ]);
    }
}
