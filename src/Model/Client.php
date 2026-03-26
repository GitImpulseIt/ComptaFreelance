<?php

declare(strict_types=1);

namespace App\Model;

class Client
{
    public ?int $id = null;
    public int $entreprise_id = 0;
    public string $nom = '';
    public string $email = '';
    public string $adresse = '';
    public string $code_postal = '';
    public string $ville = '';
    public string $siret = '';
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
