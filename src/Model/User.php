<?php

declare(strict_types=1);

namespace App\Model;

class User
{
    public ?int $id = null;
    public string $email = '';
    public string $password = '';
    public string $nom = '';
    public string $prenom = '';
    public string $role = 'freelance'; // freelance | admin
    public ?int $entreprise_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
