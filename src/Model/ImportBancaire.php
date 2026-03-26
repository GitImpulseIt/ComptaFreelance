<?php

declare(strict_types=1);

namespace App\Model;

class ImportBancaire
{
    public ?int $id = null;
    public int $compte_bancaire_id = 0;
    public string $source = 'fichier'; // fichier | api
    public string $format = '';         // csv | ofx | qif | api
    public ?string $fichier = null;
    public int $nb_transactions = 0;
    public string $statut = 'en_cours'; // en_cours | termine | erreur
    public ?string $created_at = null;
}
