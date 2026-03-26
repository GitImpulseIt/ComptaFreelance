<?php

declare(strict_types=1);

namespace App\Model;

class TransactionBancaire
{
    public ?int $id = null;
    public int $compte_bancaire_id = 0;
    public int $import_bancaire_id = 0;
    public string $date = '';
    public string $libelle = '';
    public float $montant = 0.0;
    public string $type = 'debit'; // debit | credit
    public string $statut = 'non_rapproche'; // non_rapproche | rapproche | ignore
    public ?int $facture_id = null;
    public ?int $depense_id = null;
    public ?string $created_at = null;
}
