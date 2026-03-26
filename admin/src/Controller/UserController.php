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

    public function create(): void
    {
        echo $this->twig->render('users/form.html.twig', [
            'user' => [],
            'entreprises' => $this->service->getAllEntreprises(),
            'error' => $_SESSION['user_error'] ?? null,
        ]);
        unset($_SESSION['user_error']);
    }

    public function store(): void
    {
        $email = $_POST['email'] ?? '';

        if ($this->service->emailExists($email)) {
            $_SESSION['user_error'] = 'Cette adresse email est déjà utilisée.';
            header('Location: /users/create');
            exit;
        }

        $password = $_POST['password'] ?? '';
        if (strlen($password) < 8) {
            $_SESSION['user_error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            header('Location: /users/create');
            exit;
        }

        $this->service->create([
            'entreprise_id' => (int) ($_POST['entreprise_id'] ?? 0),
            'email' => $email,
            'nom' => $_POST['nom'] ?? '',
            'prenom' => $_POST['prenom'] ?? '',
        ], $password);

        header('Location: /users');
        exit;
    }

    public function show(int $id): void
    {
        $user = $this->service->findById($id);
        if (!$user) { header('Location: /users'); exit; }
        echo $this->twig->render('users/show.html.twig', ['user' => $user]);
    }

    public function edit(int $id): void
    {
        $user = $this->service->findById($id);
        if (!$user) { header('Location: /users'); exit; }
        echo $this->twig->render('users/form.html.twig', [
            'user' => $user,
            'entreprises' => $this->service->getAllEntreprises(),
            'error' => $_SESSION['user_error'] ?? null,
        ]);
        unset($_SESSION['user_error']);
    }

    public function update(int $id): void
    {
        $email = $_POST['email'] ?? '';

        if ($this->service->emailExists($email, $id)) {
            $_SESSION['user_error'] = 'Cette adresse email est déjà utilisée.';
            header('Location: /users/' . $id . '/edit');
            exit;
        }

        $this->service->update($id, [
            'entreprise_id' => (int) ($_POST['entreprise_id'] ?? 0),
            'email' => $email,
            'nom' => $_POST['nom'] ?? '',
            'prenom' => $_POST['prenom'] ?? '',
        ]);

        header('Location: /users/' . $id);
        exit;
    }

    public function delete(int $id): void
    {
        $this->service->delete($id);
        header('Location: /users');
        exit;
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
