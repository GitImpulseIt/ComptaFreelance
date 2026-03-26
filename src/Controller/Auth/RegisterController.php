<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Middleware\AuthMiddleware;
use App\Repository\UserRepository;
use PDO;
use Twig\Environment;

class RegisterController
{
    public function __construct(
        private Environment $twig,
        private AuthMiddleware $auth,
        private UserRepository $userRepository,
        private PDO $pdo,
    ) {}

    public function showRegister(): void
    {
        if ($this->auth->isAuthenticated()) {
            header('Location: /app');
            exit;
        }

        $entreprises = $this->pdo->query(
            'SELECT id, raison_sociale FROM entreprises WHERE active = true ORDER BY raison_sociale'
        )->fetchAll();

        echo $this->twig->render('auth/register.html.twig', [
            'error' => $_SESSION['register_error'] ?? null,
            'entreprises' => $entreprises,
        ]);
        unset($_SESSION['register_error']);
    }

    public function register(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $entrepriseId = (int) ($_POST['entreprise_id'] ?? 0);

        if (strlen($password) < 8) {
            $_SESSION['register_error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            header('Location: /auth/register');
            exit;
        }

        if ($this->userRepository->emailExists($email)) {
            $_SESSION['register_error'] = 'Cette adresse email est déjà utilisée.';
            header('Location: /auth/register');
            exit;
        }

        if ($entrepriseId === 0) {
            $_SESSION['register_error'] = 'Veuillez sélectionner une entreprise.';
            header('Location: /auth/register');
            exit;
        }

        $userId = $this->userRepository->create([
            'entreprise_id' => $entrepriseId,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'nom' => $nom,
            'prenom' => $prenom,
        ]);

        $user = $this->userRepository->findById($userId);
        $this->auth->login($user);
        header('Location: /app');
        exit;
    }
}
