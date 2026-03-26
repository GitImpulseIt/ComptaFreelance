<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Chargement de la configuration
$config = require dirname(__DIR__) . '/config/app.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';
$twigConfig = require dirname(__DIR__) . '/config/twig.php';
$routes = require dirname(__DIR__) . '/config/routes.php';

// Initialisation Twig
$loader = new \Twig\Loader\FilesystemLoader($twigConfig['templates_path']);
$twig = new \Twig\Environment($loader, [
    'cache' => $twigConfig['cache'],
    'debug' => $twigConfig['debug'],
    'auto_reload' => $twigConfig['auto_reload'],
]);

// Connexion BDD
$dsn = sprintf(
    '%s:host=%s;port=%s;dbname=%s;charset=%s',
    $dbConfig['driver'],
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['database'],
    $dbConfig['charset']
);
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

// Routing simple
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// TODO: Implémenter le router avec résolution des paramètres {id}
// TODO: Implémenter les middlewares (Auth, Admin, CSRF, Entreprise)
// TODO: Dispatcher vers le bon contrôleur

http_response_code(200);
echo $twig->render('app/dashboard/index.html.twig', [
    'title' => 'ComptaV2 - Dashboard',
]);
