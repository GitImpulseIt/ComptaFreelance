<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Middleware\AuthMiddleware;
use App\Repository\UserRepository;
use Twig\Environment;

class LoginController
{
    public function __construct(
        private Environment $twig,
        private AuthMiddleware $auth,
        private UserRepository $userRepository,
    ) {}

    public function showLogin(): void
    {
        if ($this->auth->isAuthenticated()) {
            header('Location: /app');
            exit;
        }
        echo $this->twig->render('auth/login.html.twig', [
            'error' => $_SESSION['login_error'] ?? null,
        ]);
        unset($_SESSION['login_error']);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$this->auth->verifyPassword($password, $user['password'])) {
            $_SESSION['login_error'] = 'Email ou mot de passe incorrect.';
            header('Location: /auth/login');
            exit;
        }

        $this->auth->login($user);
        header('Location: /app');
        exit;
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /auth/login');
        exit;
    }
}
