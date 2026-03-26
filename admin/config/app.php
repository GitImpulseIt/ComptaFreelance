<?php

return [
    'name' => 'ComptaV2 Admin',
    'env' => getenv('APP_ENV') ?: 'dev',
    'debug' => getenv('APP_ENV') !== 'prod',
    'timezone' => 'Europe/Paris',
];
