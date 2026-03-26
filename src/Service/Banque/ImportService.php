<?php

declare(strict_types=1);

namespace App\Service\Banque;

use App\Repository\ImportBancaireRepository;
use App\Repository\TransactionBancaireRepository;

class ImportService
{
    public function __construct(
        private ParserFactory $parserFactory,
        private ImportBancaireRepository $importRepository,
        private TransactionBancaireRepository $transactionRepository,
    ) {}

    public function importerFichier(int $compteId, string $filePath, string $format): int
    {
        // TODO: Parser le fichier, créer l'import, persister les transactions
        return 0;
    }
}
