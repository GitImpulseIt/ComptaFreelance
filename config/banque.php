<?php

return [
    // Formats d'import supportés
    'formats' => ['csv', 'ofx', 'qif'],

    // Taille max des fichiers d'import (en octets)
    'max_file_size' => 10 * 1024 * 1024, // 10 Mo

    // Répertoire de stockage des fichiers importés
    'upload_path' => dirname(__DIR__) . '/storage/uploads/banque',

    // Provider API bancaire (Bridge, GoCardless, etc.)
    'provider' => [
        'name' => getenv('BANK_PROVIDER') ?: 'none',
        'client_id' => getenv('BANK_CLIENT_ID') ?: '',
        'client_secret' => getenv('BANK_CLIENT_SECRET') ?: '',
        'sandbox' => getenv('APP_ENV') !== 'prod',
    ],
];
