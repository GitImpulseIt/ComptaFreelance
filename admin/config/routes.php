<?php

return [

    // Auth
    'GET /' => ['AuthController', 'showLogin'],
    'POST /login' => ['AuthController', 'login'],
    'POST /logout' => ['AuthController', 'logout'],

    // Dashboard
    'GET /dashboard' => ['DashboardController', 'index'],

    // Entreprises
    'GET /entreprises' => ['EntrepriseController', 'index'],
    'GET /entreprises/create' => ['EntrepriseController', 'create'],
    'POST /entreprises' => ['EntrepriseController', 'store'],
    'GET /entreprises/{id}' => ['EntrepriseController', 'show'],
    'GET /entreprises/{id}/edit' => ['EntrepriseController', 'edit'],
    'POST /entreprises/{id}' => ['EntrepriseController', 'update'],
    'POST /entreprises/{id}/suspend' => ['EntrepriseController', 'suspend'],

    // Utilisateurs
    'GET /users' => ['UserController', 'index'],
    'GET /users/{id}' => ['UserController', 'show'],
    'POST /users/{id}/reset-password' => ['UserController', 'resetPassword'],

    // Banque (supervision)
    'GET /banque' => ['BanqueController', 'index'],
    'GET /banque/{id}' => ['BanqueController', 'show'],

    // Mot de passe admin
    'GET /password' => ['PasswordController', 'showChange'],
    'POST /password' => ['PasswordController', 'change'],

];
