<?php

declare(strict_types=1);

namespace App\Model;

class Entreprise
{
    public ?int $id = null;
    public string $raison_sociale = '';
    public string $siret = '';
    public string $adresse = '';
    public string $code_postal = '';
    public string $ville = '';
    public string $telephone = '';
    public string $email = '';
    public string $regime_tva = 'franchise'; // franchise | reel_simplifie | reel_normal
    public bool $active = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
