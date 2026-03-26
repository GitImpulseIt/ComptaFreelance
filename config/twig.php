<?php

return [
    'templates_path' => dirname(__DIR__) . '/templates',
    'cache' => getenv('APP_ENV') === 'prod'
        ? dirname(__DIR__) . '/storage/cache/twig'
        : false,
    'debug' => getenv('APP_ENV') !== 'prod',
    'auto_reload' => true,
];
