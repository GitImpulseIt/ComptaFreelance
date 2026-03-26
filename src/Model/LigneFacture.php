<?php

declare(strict_types=1);

namespace App\Model;

class LigneFacture
{
    public ?int $id = null;
    public int $facture_id = 0;
    public string $description = '';
    public float $quantite = 1.0;
    public float $prix_unitaire = 0.0;
    public float $montant_ht = 0.0;
}
