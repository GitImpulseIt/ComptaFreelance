<?php

declare(strict_types=1);

namespace App\Service;

class TvaService
{
    public function calculerTva(float $montantHt, float $taux): float
    {
        return round($montantHt * $taux / 100, 2);
    }

    public function calculerTtc(float $montantHt, float $taux): float
    {
        return round($montantHt * (1 + $taux / 100), 2);
    }
}
