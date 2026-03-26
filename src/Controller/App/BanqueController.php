<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use PDO;
use Twig\Environment;

class BanqueController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {}

    public function index(): void {}
    public function showImport(): void {}
    public function import(): void {}
    public function showConnect(): void {}
    public function connect(): void {}
    public function transactions(): void {}
    public function rapprocher(int $id): void {}
}
