<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

session_start();

// Configuration
$config = require dirname(__DIR__) . '/config/app.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';
$twigConfig = require dirname(__DIR__) . '/config/twig.php';
$routes = require dirname(__DIR__) . '/config/routes.php';

// Twig
$loader = new \Twig\Loader\FilesystemLoader($twigConfig['templates_path']);
$twig = new \Twig\Environment($loader, [
    'cache' => $twigConfig['cache'],
    'debug' => $twigConfig['debug'],
    'auto_reload' => $twigConfig['auto_reload'],
]);
$twig->addGlobal('session', $_SESSION);

// BDD
$dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $dbConfig['driver'], $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
$pdo->exec("SET client_encoding TO '" . $dbConfig['charset'] . "'");

// Services
$userRepository = new \App\Repository\UserRepository($pdo);
$authMiddleware = new \App\Middleware\AuthMiddleware($userRepository);

// Routing
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Redirect racine
if ($uri === '/') {
    header('Location: ' . ($authMiddleware->isAuthenticated() ? '/app' : '/auth/login'));
    exit;
}

// Routes publiques (auth/*)
$isPublicRoute = str_starts_with($uri, '/auth/');

// Protection des routes /app/*
if (!$isPublicRoute && !$authMiddleware->isAuthenticated()) {
    header('Location: /auth/login');
    exit;
}

// Injecter user et entreprise dans Twig pour les routes protégées
if ($authMiddleware->isAuthenticated()) {
    $twig->addGlobal('current_user', $authMiddleware->getUser());
    $entrepriseId = $authMiddleware->getEntrepriseId();
    $entrepriseData = ['id' => $entrepriseId, 'raison_sociale' => $_SESSION['user']['entreprise_nom'] ?? ''];
    if ($entrepriseId) {
        $stmtE = $pdo->prepare("SELECT regime_tva FROM entreprises WHERE id = :id");
        $stmtE->execute(['id' => $entrepriseId]);
        $entrepriseData['regime_tva'] = $stmtE->fetchColumn() ?: 'franchise';
    }
    $twig->addGlobal('entreprise', $entrepriseData);
}

// Router avec support des paramètres {id}
$matched = false;
foreach ($routes as $pattern => $handler) {
    [$routeMethod, $routePath] = explode(' ', $pattern, 2);
    if ($routeMethod !== $method) continue;

    $regex = preg_replace('#\{(\w+)\}#', '(\d+)', $routePath);
    if (preg_match('#^' . $regex . '$#', $uri, $matches)) {
        $matched = true;
        array_shift($matches);
        [$controllerName, $action] = $handler;

        $controller = match ($controllerName) {
            // Auth
            'Auth\\LoginController' => new \App\Controller\Auth\LoginController($twig, $authMiddleware, $userRepository),
            'Auth\\RegisterController' => new \App\Controller\Auth\RegisterController($twig, $authMiddleware, $userRepository, $pdo),
            // App
            'App\\DashboardController' => new \App\Controller\App\DashboardController($twig, $pdo, $authMiddleware),
            'App\\ClientController' => new \App\Controller\App\ClientController($twig, $pdo, $authMiddleware),
            'App\\FactureController' => new \App\Controller\App\FactureController($twig, $pdo, $authMiddleware),
            'App\\DepenseController' => new \App\Controller\App\DepenseController($twig, $pdo, $authMiddleware),
            'App\\BanqueController' => new \App\Controller\App\BanqueController($twig, $pdo, $authMiddleware),
            'App\\TvaController' => new \App\Controller\App\TvaController($twig, $pdo, $authMiddleware),
            'App\\ParametreController' => new \App\Controller\App\ParametreController($twig, $pdo, $authMiddleware),
        };

        $controller->$action(...array_map('intval', $matches));
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo '404 - Page introuvable';
}
