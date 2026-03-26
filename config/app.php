<?php

return [
    'name' => 'ComptaV2',
    'env' => getenv('APP_ENV') ?: 'dev',
    'debug' => getenv('APP_ENV') !== 'prod',
    'timezone' => 'Europe/Paris',
    'locale' => 'fr_FR',
];
