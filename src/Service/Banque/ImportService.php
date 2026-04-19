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

    /**
     * @return array{inserted: int, skipped: int}
     */
    public function importerFichier(int $compteId, string $filePath, string $format): array
    {
        $importId = $this->importRepository->create([
            'compte_bancaire_id' => $compteId,
            'source' => 'fichier',
            'format' => $format,
            'fichier' => basename($filePath),
        ]);

        try {
            $parser = $this->parserFactory->create($format);
            $rows = $parser->parse($filePath);

            if (empty($rows)) {
                $this->importRepository->updateStatut($importId, 'termine', 0);
                return ['inserted' => 0, 'skipped' => 0];
            }

            $transactions = [];
            foreach ($rows as $row) {
                $transactions[] = [
                    'compte_bancaire_id' => $compteId,
                    'import_bancaire_id' => $importId,
                    'date' => $row['date'],
                    'libelle' => $row['libelle'],
                    'montant' => $row['montant'],
                    'type' => $row['type'],
                    'reference_externe' => $row['reference_externe'] ?? null,
                ];
            }

            $result = $this->transactionRepository->createBatch($transactions);
            $this->importRepository->updateStatut($importId, 'termine', $result['inserted']);

            return $result;
        } catch (\Throwable $e) {
            $this->importRepository->updateStatut($importId, 'erreur', 0);
            throw $e;
        }
    }
}
