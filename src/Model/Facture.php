<?php

declare(strict_types=1);

namespace App\Model;

class Facture
{
    public ?int $id = null;
    public int $entreprise_id = 0;
    public int $client_id = 0;
    public string $numero = '';
    public string $date_emission = '';
    public string $date_echeance = '';
    public string $statut = 'brouillon'; // brouillon | envoyee | payee | annulee
    public float $montant_ht = 0.0;
    public float $taux_tva = 0.0;
    public float $montant_ttc = 0.0;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
