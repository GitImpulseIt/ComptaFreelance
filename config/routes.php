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
    'POST /app/quotient' => ['App\\DashboardController', 'updateQuotient'],

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
    'GET /app/banque' => ['App\\BanqueController', 'transactions'],
    'GET /app/banque/export-csv' => ['App\\BanqueController', 'exportCsv'],
    'GET /app/banque/import' => ['App\\BanqueController', 'showImport'],
    'POST /app/banque/import' => ['App\\BanqueController', 'import'],
    'GET /app/banque/{id}' => ['App\\BanqueController', 'show'],
    'POST /app/banque/{id}' => ['App\\BanqueController', 'qualify'],
    'POST /app/banque/{id}/liens' => ['App\\BanqueController', 'addLien'],
    'POST /app/banque/{id}/liens/delete' => ['App\\BanqueController', 'deleteLien'],
    'POST /app/banque/{id}/rapprocher' => ['App\\BanqueController', 'rapprocher'],

    // App - TVA
    'GET /app/tva' => ['App\\TvaController', 'index'],
    'POST /app/tva/payer' => ['App\\TvaController', 'payer'],
    'POST /app/tva/date-paiement' => ['App\\TvaController', 'updateDatePaiement'],

    // App - Clôture d'exercice
    'GET /app/cloture' => ['App\\ClotureController', 'index'],
    'GET /app/cloture/bilan' => ['App\\ClotureController', 'tabBilan'],
    'GET /app/cloture/compte-resultat' => ['App\\ClotureController', 'tabCompteResultat'],
    'GET /app/cloture/compte-resultat/detail' => ['App\\ClotureController', 'detailCompteResultat'],
    'GET /app/cloture/2035' => ['App\\ClotureController', 'tab2035'],
    'POST /app/cloture/save' => ['App\\ClotureController', 'save'],

    // App - Immobilisations
    'GET /app/immobilisations' => ['App\\ImmobilisationController', 'index'],
    'GET /app/immobilisations/create' => ['App\\ImmobilisationController', 'create'],
    'POST /app/immobilisations' => ['App\\ImmobilisationController', 'store'],
    'GET /app/immobilisations/{id}/edit' => ['App\\ImmobilisationController', 'edit'],
    'POST /app/immobilisations/{id}' => ['App\\ImmobilisationController', 'update'],
    'POST /app/immobilisations/{id}/delete' => ['App\\ImmobilisationController', 'delete'],

    // App - Paramètres
    'GET /app/parametres' => ['App\\ParametreController', 'index'],
    'POST /app/parametres' => ['App\\ParametreController', 'update'],

    // App - Paramètres > Entreprise
    'GET /app/parametres/entreprise' => ['App\\ParametreController', 'entreprise'],
    'POST /app/parametres/entreprise' => ['App\\ParametreController', 'updateEntreprise'],

    // App - Paramètres > Comptes bancaires
    'GET /app/parametres/comptes-bancaires' => ['App\\ParametreController', 'comptesBancaires'],
    'GET /app/parametres/comptes-bancaires/create' => ['App\\ParametreController', 'createCompte'],
    'POST /app/parametres/comptes-bancaires' => ['App\\ParametreController', 'storeCompte'],
    'GET /app/parametres/comptes-bancaires/{id}/edit' => ['App\\ParametreController', 'editCompte'],
    'POST /app/parametres/comptes-bancaires/{id}' => ['App\\ParametreController', 'updateCompte'],
    'POST /app/parametres/comptes-bancaires/{id}/delete' => ['App\\ParametreController', 'deleteCompte'],

];
