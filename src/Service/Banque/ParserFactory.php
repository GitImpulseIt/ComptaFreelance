<?php

declare(strict_types=1);

namespace App\Service\Banque;

use App\Service\Banque\Parser\ParserInterface;
use App\Service\Banque\Parser\CsvParser;
use App\Service\Banque\Parser\OfxParser;
use App\Service\Banque\Parser\QifParser;

class ParserFactory
{
    public function create(string $format): ParserInterface
    {
        return match ($format) {
            'csv' => new CsvParser(),
            'ofx' => new OfxParser(),
            'qif' => new QifParser(),
            default => throw new \InvalidArgumentException("Format non supporté : {$format}"),
        };
    }
}
