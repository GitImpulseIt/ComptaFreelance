<?php

declare(strict_types=1);

namespace App\Service\Banque\Parser;

class CsvShineParser implements ParserInterface
{
    public function parse(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier : {$filePath}");
        }

        // Lire l'en-tête
        $header = fgetcsv($handle, 0, ';', '"', '\\');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('Fichier CSV vide ou illisible.');
        }

        // Normaliser les noms de colonnes (trim BOM + espaces)
        $header = array_map(function (string $col): string {
            return trim($col, "\xEF\xBB\xBF \t\n\r\0\x0B");
        }, $header);

        $transactions = [];

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (count($row) < count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            if ($data === false) {
                continue;
            }

            $debit = $this->parseDecimal($data['Débit'] ?? '0');
            $credit = $this->parseDecimal($data['Crédit'] ?? '0');

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $isCredit = $credit > 0;
            $montant = $isCredit ? $credit : $debit;

            $transactions[] = [
                'date' => $this->parseDate($data["Date d'opération"] ?? $data['Date de la valeur'] ?? ''),
                'libelle' => trim($data['Libellé'] ?? ''),
                'montant' => round($montant, 2),
                'type' => $isCredit ? 'credit' : 'debit',
                'contrepartie' => trim($data['Nom de la contrepartie'] ?? ''),
                'reference_externe' => trim($data['Transaction ID'] ?? ''),
            ];
        }

        fclose($handle);

        return $transactions;
    }

    private function parseDecimal(string $value): float
    {
        $value = trim($value, '" ');
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    private function parseDate(string $value): string
    {
        $value = trim($value, '" ');

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $value;
    }
}
