<?php

declare(strict_types=1);

namespace App\Service\Banque;

use App\Service\Banque\Parser\ParserInterface;
use App\Service\Banque\Parser\CsvShineParser;

class ParserFactory
{
    public function create(string $format): ParserInterface
    {
        return match ($format) {
            'csv_shine' => new CsvShineParser(),
            default => throw new \InvalidArgumentException("Format non supporté : {$format}"),
        };
    }
}
