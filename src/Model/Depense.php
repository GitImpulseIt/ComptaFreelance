<?php

declare(strict_types=1);

namespace App\Model;

class Depense
{
    public ?int $id = null;
    public int $entreprise_id = 0;
    public int $categorie_id = 0;
    public string $date = '';
    public string $libelle = '';
    public float $montant_ht = 0.0;
    public float $taux_tva = 0.0;
    public float $montant_ttc = 0.0;
    public ?int $transaction_bancaire_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
