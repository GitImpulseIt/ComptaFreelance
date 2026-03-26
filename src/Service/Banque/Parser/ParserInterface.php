<?php

declare(strict_types=1);

namespace App\Service\Banque\Parser;

interface ParserInterface
{
    /**
     * Parse un fichier bancaire et retourne un tableau de transactions.
     *
     * @return array<array{date: string, libelle: string, montant: float, type: string}>
     */
    public function parse(string $filePath): array;
}
