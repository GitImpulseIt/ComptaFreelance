<?php

declare(strict_types=1);

namespace App\Service\Banque;

use App\Repository\TransactionBancaireRepository;
use App\Repository\FactureRepository;
use App\Repository\DepenseRepository;

class RapprocherService
{
    public function __construct(
        private TransactionBancaireRepository $transactionRepository,
        private FactureRepository $factureRepository,
        private DepenseRepository $depenseRepository,
    ) {}

    public function rapprocherAuto(int $entrepriseId): int
    {
        // TODO: Matcher les transactions non rapprochées avec factures/dépenses
        // Critères : montant exact + date proche + libellé similaire
        return 0;
    }

    public function rapprocherManuel(int $transactionId, ?int $factureId, ?int $depenseId): void
    {
        $this->transactionRepository->rapprocher($transactionId, $factureId, $depenseId);
    }
}
