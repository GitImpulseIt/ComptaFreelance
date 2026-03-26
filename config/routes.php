<?php

return [

    // Auth
    'GET /auth/login' => ['Auth\\LoginController', 'showLogin'],
    'POST /auth/login' => ['Auth\\LoginController', 'login'],
    'GET /auth/register' => ['Auth\\RegisterController', 'showRegister'],
    'POST /auth/register' => ['Auth\\RegisterController', 'register'],
    'POST /auth/logout' => ['Auth\\LoginController', 'logout'],

    // App - Dashboard
    'GET /app' => ['App\\DashboardController', 'index'],

    // App - Clients
    'GET /app/clients' => ['App\\ClientController', 'index'],
    'GET /app/clients/create' => ['App\\ClientController', 'create'],
    'POST /app/clients' => ['App\\ClientController', 'store'],
    'GET /app/clients/{id}' => ['App\\ClientController', 'show'],
    'GET /app/clients/{id}/edit' => ['App\\ClientController', 'edit'],
    'POST /app/clients/{id}' => ['App\\ClientController', 'update'],
    'POST /app/clients/{id}/delete' => ['App\\ClientController', 'delete'],

    // App - Factures
    'GET /app/factures' => ['App\\FactureController', 'index'],
    'GET /app/factures/create' => ['App\\FactureController', 'create'],
    'POST /app/factures' => ['App\\FactureController', 'store'],
    'GET /app/factures/{id}' => ['App\\FactureController', 'show'],
    'GET /app/factures/{id}/edit' => ['App\\FactureController', 'edit'],
    'POST /app/factures/{id}' => ['App\\FactureController', 'update'],
    'POST /app/factures/{id}/delete' => ['App\\FactureController', 'delete'],

    // App - Dépenses
    'GET /app/depenses' => ['App\\DepenseController', 'index'],
    'GET /app/depenses/create' => ['App\\DepenseController', 'create'],
    'POST /app/depenses' => ['App\\DepenseController', 'store'],
    'GET /app/depenses/{id}' => ['App\\DepenseController', 'show'],
    'GET /app/depenses/{id}/edit' => ['App\\DepenseController', 'edit'],
    'POST /app/depenses/{id}' => ['App\\DepenseController', 'update'],
    'POST /app/depenses/{id}/delete' => ['App\\DepenseController', 'delete'],

    // App - Banque
    'GET /app/banque' => ['App\\BanqueController', 'index'],
    'GET /app/banque/import' => ['App\\BanqueController', 'showImport'],
    'POST /app/banque/import' => ['App\\BanqueController', 'import'],
    'GET /app/banque/connect' => ['App\\BanqueController', 'showConnect'],
    'POST /app/banque/connect' => ['App\\BanqueController', 'connect'],
    'GET /app/banque/transactions' => ['App\\BanqueController', 'transactions'],
    'POST /app/banque/transactions/{id}/rapprocher' => ['App\\BanqueController', 'rapprocher'],

    // App - TVA
    'GET /app/tva' => ['App\\TvaController', 'index'],

    // App - Paramètres
    'GET /app/parametres' => ['App\\ParametreController', 'index'],
    'POST /app/parametres' => ['App\\ParametreController', 'update'],

    // Admin - Dashboard
    'GET /admin' => ['Admin\\AdminDashboardController', 'index'],

    // Admin - Entreprises
    'GET /admin/entreprises' => ['Admin\\EntrepriseController', 'index'],
    'GET /admin/entreprises/create' => ['Admin\\EntrepriseController', 'create'],
    'POST /admin/entreprises' => ['Admin\\EntrepriseController', 'store'],
    'GET /admin/entreprises/{id}' => ['Admin\\EntrepriseController', 'show'],
    'GET /admin/entreprises/{id}/edit' => ['Admin\\EntrepriseController', 'edit'],
    'POST /admin/entreprises/{id}' => ['Admin\\EntrepriseController', 'update'],
    'POST /admin/entreprises/{id}/suspend' => ['Admin\\EntrepriseController', 'suspend'],

    // Admin - Utilisateurs
    'GET /admin/users' => ['Admin\\AdminUserController', 'index'],
    'GET /admin/users/{id}' => ['Admin\\AdminUserController', 'show'],
    'POST /admin/users/{id}' => ['Admin\\AdminUserController', 'update'],

    // Admin - Banque (supervision)
    'GET /admin/banque' => ['Admin\\AdminBanqueController', 'index'],
    'GET /admin/banque/{id}' => ['Admin\\AdminBanqueController', 'show'],

];
