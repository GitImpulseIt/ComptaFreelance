<?php

return [
    'driver' => 'pgsql',
    'host' => getenv('POSTGRES_HOST') ?: 'postgres',
    'port' => getenv('POSTGRES_PORT') ?: '5432',
    'database' => getenv('POSTGRES_DB') ?: 'comptav2',
    'username' => getenv('POSTGRES_USER') ?: 'comptav2',
    'password' => getenv('POSTGRES_PASSWORD') ?: 'changeme',
    'charset' => 'utf8',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
