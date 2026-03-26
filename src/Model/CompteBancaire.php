<?php

declare(strict_types=1);

namespace App\Model;

class CompteBancaire
{
    public ?int $id = null;
    public int $entreprise_id = 0;
    public string $nom = '';
    public string $banque = '';
    public string $iban = '';
    public string $type_connexion = 'manuel'; // manuel | api
    public ?string $provider_account_id = null;
    public ?string $derniere_synchro = null;
    public bool $active = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
