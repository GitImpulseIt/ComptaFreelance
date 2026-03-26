<?php

declare(strict_types=1);

namespace Admin\Controller;

use Admin\Service\PasswordService;
use Twig\Environment;

class PasswordController
{
    public function __construct(
        private Environment $twig,
        private PasswordService $passwordService,
        private array $authConfig,
    ) {}

    public function showChange(): void
    {
        echo $this->twig->render('password/change.html.twig', [
            'error' => $_SESSION['password_error'] ?? null,
            'success' => $_SESSION['password_success'] ?? null,
        ]);
        unset($_SESSION['password_error'], $_SESSION['password_success']);
    }

    public function change(): void
    {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            $_SESSION['password_error'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
            header('Location: /password');
            exit;
        }

        if ($new !== $confirm) {
            $_SESSION['password_error'] = 'Les mots de passe ne correspondent pas.';
            header('Location: /password');
            exit;
        }

        if ($this->passwordService->changeAdminPassword($current, $new, $this->authConfig['password_hash'])) {
            $_SESSION['password_success'] = 'Mot de passe modifié avec succès.';
        } else {
            $_SESSION['password_error'] = 'Mot de passe actuel incorrect.';
        }

        header('Location: /password');
        exit;
    }
}
