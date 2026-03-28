<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

session_start();

// Configuration
$appConfig = require dirname(__DIR__) . '/config/app.php';
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
$authMiddleware = new \Admin\Middleware\AuthMiddleware($pdo);
$passwordService = new \Admin\Service\PasswordService($pdo);
$entrepriseService = new \Admin\Service\EntrepriseService($pdo);
$userService = new \Admin\Service\UserService($pdo, $passwordService);
$banqueService = new \Admin\Service\BanqueService($pdo);

// Routing
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Routes publiques (pas besoin d'auth)
$publicRoutes = ['/', 'POST /login'];
$routeKey = $method . ' ' . $uri;
$isPublicRoute = in_array($routeKey, $publicRoutes) || in_array($uri, ['/']);

// Vérification auth pour les routes protégées
if (!$isPublicRoute && !$authMiddleware->isAuthenticated()) {
    header('Location: /');
    exit;
}

// Router simple avec support des paramètres {id}
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
            'AuthController' => new \Admin\Controller\AuthController($twig, $authMiddleware),
            'DashboardController' => new \Admin\Controller\DashboardController($twig, $pdo),
            'EntrepriseController' => new \Admin\Controller\EntrepriseController($twig, $entrepriseService),
            'UserController' => new \Admin\Controller\UserController($twig, $userService),
            'BanqueController' => new \Admin\Controller\BanqueController($twig, $banqueService),
            'PasswordController' => new \Admin\Controller\PasswordController($twig, $passwordService),
            'IrController' => new \Admin\Controller\IrController($twig, $pdo),
            'TvaCalendrierController' => new \Admin\Controller\TvaCalendrierController($twig, $pdo),
        };

        $controller->$action(...array_map('intval', $matches));
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo $twig->render('layout/404.html.twig');
}
