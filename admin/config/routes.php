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
    'POST /entreprises/{id}/delete' => ['EntrepriseController', 'delete'],

    // Utilisateurs
    'GET /users' => ['UserController', 'index'],
    'GET /users/create' => ['UserController', 'create'],
    'POST /users' => ['UserController', 'store'],
    'GET /users/{id}' => ['UserController', 'show'],
    'GET /users/{id}/edit' => ['UserController', 'edit'],
    'POST /users/{id}' => ['UserController', 'update'],
    'POST /users/{id}/delete' => ['UserController', 'delete'],
    'POST /users/{id}/reset-password' => ['UserController', 'resetPassword'],

    // Banque (supervision)
    'GET /banque' => ['BanqueController', 'index'],
    'GET /banque/{id}' => ['BanqueController', 'show'],

    // Calendrier TVA
    'GET /tva-calendrier' => ['TvaCalendrierController', 'index'],
    'GET /tva-calendrier/create' => ['TvaCalendrierController', 'create'],
    'POST /tva-calendrier' => ['TvaCalendrierController', 'store'],
    'GET /tva-calendrier/{id}/edit' => ['TvaCalendrierController', 'edit'],
    'POST /tva-calendrier/{id}' => ['TvaCalendrierController', 'update'],
    'POST /tva-calendrier/{id}/delete' => ['TvaCalendrierController', 'delete'],

    // Barème IR
    'GET /ir' => ['IrController', 'index'],
    'GET /ir/create' => ['IrController', 'create'],
    'POST /ir' => ['IrController', 'store'],
    'GET /ir/{id}/edit' => ['IrController', 'edit'],
    'POST /ir/{id}' => ['IrController', 'update'],
    'POST /ir/{id}/delete' => ['IrController', 'delete'],
    'POST /ir/{id}/duplicate' => ['IrController', 'duplicate'],

    // Plan comptable
    'GET /plan-comptable' => ['PlanComptableController', 'index'],
    'GET /plan-comptable/create' => ['PlanComptableController', 'create'],
    'POST /plan-comptable' => ['PlanComptableController', 'store'],
    'GET /plan-comptable/{numero}/edit' => ['PlanComptableController', 'edit'],
    'POST /plan-comptable/{numero}' => ['PlanComptableController', 'update'],
    'POST /plan-comptable/{numero}/delete' => ['PlanComptableController', 'delete'],

    // Mot de passe admin
    'GET /password' => ['PasswordController', 'showChange'],
    'POST /password' => ['PasswordController', 'change'],

];
