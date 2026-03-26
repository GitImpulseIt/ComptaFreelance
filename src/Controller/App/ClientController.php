<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use PDO;
use Twig\Environment;

class ClientController
{
    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {}

    public function index(): void {}
    public function create(): void {}
    public function store(): void {}
    public function show(int $id): void {}
    public function edit(int $id): void {}
    public function update(int $id): void {}
    public function delete(int $id): void {}
}
