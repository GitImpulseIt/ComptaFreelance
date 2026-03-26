<?php

declare(strict_types=1);

namespace Admin\Controller;

use Admin\Middleware\AuthMiddleware;
use Twig\Environment;

class AuthController
{
    public function __construct(
        private Environment $twig,
        private AuthMiddleware $auth,
    ) {}

    public function showLogin(): void
    {
        if ($this->auth->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }
        echo $this->twig->render('auth/login.html.twig', [
            'error' => $_SESSION['login_error'] ?? null,
        ]);
        unset($_SESSION['login_error']);
    }

    public function login(): void
    {
        $password = $_POST['password'] ?? '';

        if ($this->auth->authenticate($password)) {
            header('Location: /dashboard');
        } else {
            $_SESSION['login_error'] = 'Mot de passe incorrect.';
            header('Location: /');
        }
        exit;
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /');
        exit;
    }
}
