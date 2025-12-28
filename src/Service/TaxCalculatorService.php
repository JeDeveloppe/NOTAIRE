<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TaxCalculatorService
{
    private array $brackets;

    public function __construct(ParameterBagInterface $params)
    {
        $this->brackets = $params->get('app.tax_brackets');
    }

    public function calculateSaving(float $amount, string $relationship): float
    {
        // 1. Déterminer quel barème utiliser
        $type = $this->getBracketType($relationship);
        $selectedBrackets = $this->brackets[$type];

        $saving = 0;
        $remaining = $amount;
        $previousLimit = 0;

        // 2. Calcul progressif par tranches
        foreach ($selectedBrackets as $bracket) {
            $limit = $bracket['limit'] ?? PHP_INT_MAX;
            $rate = $bracket['rate'];
            
            $bracketSize = $limit - $previousLimit;
            $taxableInThisBracket = min($remaining, $bracketSize);

            if ($taxableInThisBracket <= 0) break;

            $saving += $taxableInThisBracket * $rate;
            $remaining -= $taxableInThisBracket;
            $previousLimit = $limit;
        }

        return $saving;
    }

    private function getBracketType(string $rel): string
    {
        return match ($rel) {
            'ENFANT', 'PETIT_ENFANT', 'CONJOINT', 'HANDICAP' => 'progressif_direct',
            'FRERE_SOEUR' => 'freres_soeurs',
            'NEVEU_NIECE' => 'neveux_nieces',
            default => 'tiers',
        };
    }
}