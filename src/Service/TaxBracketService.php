<?php

namespace App\Service;

use App\Repository\TaxBracketRepository;

class TaxBracketService
{
    public function __construct(
        private TaxBracketRepository $taxBracketRepository
    ) {}

    /**
     * Utilise le Repository pour calculer l'économie basée sur le barème progressif
     */
    public function calculateSaving(float $amount, string $relationship): float
    {
        // 1. Déterminer quel barème utiliser (catégorie en base)
        $type = $this->getBracketType($relationship);
        
        // 2. Récupérer les tranches triées par limite
        $selectedBrackets = $this->taxBracketRepository->findBy(
            ['category' => $type],
            ['amountLimit' => 'ASC']
        );

        $saving = 0;
        $remaining = $amount;
        $previousLimit = 0;

        // 3. Calcul progressif par tranches
        foreach ($selectedBrackets as $bracket) {
            // Si amountLimit est null en base, on utilise l'infini (dernière tranche)
            $limit = $bracket->getAmountLimit() ?? INF;
            $rate = $bracket->getRate();

            $bracketSize = $limit - $previousLimit;
            $taxableInThisBracket = min($remaining, $bracketSize);

            if ($taxableInThisBracket <= 0) break;

            $saving += $taxableInThisBracket * $rate;
            $remaining -= $taxableInThisBracket;
            $previousLimit = $limit;
        }

        return $saving;
    }

    /**
     * Mappe le lien de parenté vers la catégorie de l'entité TaxBracket
     */
    private function getBracketType(string $rel): string
    {
        return match ($rel) {
            'ENFANT', 'PETIT_ENFANT', 'CONJOINT', 'HANDICAP' => 'progressif_direct',
            'FRERE_SOEUR' => 'freres_soeurs',
            'NEVEU_NIECE' => 'neveux_nieces',
            default => 'tiers',
        };
    }

    /**
     * Version alternative utilisant directement la catégorie (ex: 'progressif_direct')
     */
    public function calculateTax(float $taxableAmount, string $category): float
    {
        $brackets = $this->taxBracketRepository->findBy(
            ['category' => $category],
            ['amountLimit' => 'ASC']
        );

        $tax = 0;
        $previousLimit = 0;
        $remaining = $taxableAmount;

        foreach ($brackets as $bracket) {
            $limit = $bracket->getAmountLimit() ?? INF;
            $rate = $bracket->getRate();

            $bracketSize = $limit - $previousLimit;
            $taxableInThisBracket = min($remaining, $bracketSize);

            if ($taxableInThisBracket <= 0) break;

            $tax += $taxableInThisBracket * $rate;
            $remaining -= $taxableInThisBracket;
            $previousLimit = $limit;
        }

        return $tax;
    }
}