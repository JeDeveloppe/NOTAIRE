<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Donation;
use App\Entity\DonationRule;
use App\Repository\RelationshipRepository;

class TaxOptimizationService
{
    public function __construct(
        private RelationshipRepository $relationshipRepository,
        private DonationService $donationService
    ) {}

    public function getDonationAnalyses(User $user): array
    {
        $persons = $user->getPeople();
        $analysis = [
            'expired_periods' => [], // Abattements passés non saturés
            'active_periods' => [],  // Abattements en cours (dans le délai des 15 ans)
            'total_missed' => 0      // Total du potentiel non utilisé
        ];

        foreach ($persons as $person) {
            foreach ($person->getDonationsGiven() as $donation) {
                $this->processSingleDonation($donation, $analysis);
            }
        }

        // Tri du plus récent au plus ancien
        usort($analysis['expired_periods'], fn($a, $b) => $b['startDate'] <=> $a['startDate']);
        usort($analysis['active_periods'], fn($a, $b) => $b['startDate'] <=> $a['startDate']);

        return $analysis;
    }

    private function processSingleDonation(Donation $donation, array &$analysis): void
    {
        $rule = $this->getRuleForDonation($donation);
        if (!$rule) return;

        $frequency = $rule->getFrequencyYears() ?? 15;
        $maxAllowance = (float) $rule->getAllowanceAmount();

        $startDate = $donation->getCreatedAt();
        $endDate = $startDate->modify("+$frequency years");
        $today = new \DateTimeImmutable();

        // Calcul du potentiel non optimisé pour CE don précis
        $unoptimized = max(0, $maxAllowance - $donation->getAmount());

        $data = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'donor' => $donation->getDonor()->getFirstname(),
            'beneficiary' => $donation->getBeneficiary()->getFirstname(),
            'type' => strtoupper($donation->getType()),
            'plafond' => $maxAllowance,
            'used' => $donation->getAmount(),
            'unoptimized' => $unoptimized
        ];

        if ($endDate < $today) {
            $analysis['expired_periods'][] = $data;
            $analysis['total_missed'] += $unoptimized;
        } else {
            $analysis['active_periods'][] = $data;
        }
    }

    private function getRuleForDonation(Donation $donation): ?DonationRule
    {
        $code = $this->donationService->determineRelationshipCode($donation->getDonor(), $donation->getBeneficiary());
        $relationship = $this->relationshipRepository->findOneBy(['code' => $code]);

        if (!$relationship) return null;

        foreach ($relationship->getDonationRules() as $rule) {
            if ($rule->getTaxSystem() === $donation->getType()) {
                return $rule;
            }
        }
        return null;
    }


    /**
     * Calcule toutes les opportunités de dons au sein de la famille
     * à une date donnée (permet de simuler le futur).
     */
    public function getGlobalFamilyPlan(User $user, ?\DateTimeInterface $referenceDate = null): array
    {
        // Si aucune date n'est fournie, on prend aujourd'hui
        $referenceDate = $referenceDate ?? new \DateTimeImmutable();

        $people = $user->getPeople();
        $plan = [];

        foreach ($people as $donor) {
            foreach ($people as $beneficiary) {
                if ($donor === $beneficiary) continue;

                // On passe la date de référence au simulateur
                // Note: Assurez-vous que votre DonationService supporte cet argument
                $simulation = $this->donationService->simulateMaxTaxFree($donor, $beneficiary, $referenceDate);

                foreach ($simulation['rules'] as $rule) {
                    if ($rule['is_valid'] && $rule['available'] > 0) {
                        $plan[] = [
                            'donor' => $donor,
                            'beneficiary' => $beneficiary,
                            'label' => $rule['label'],
                            'available' => $rule['available'],
                            'relationship_code' => $simulation['relationship_code'],
                            'type' => $rule['tax_system'],
                            'priority' => str_contains($rule['tax_system'], 'sarkozy') ? 1 : 2
                        ];
                    }
                }
            }
        }

        // Tri par priorité (Sarkozy d'abord) puis montant disponible
        usort($plan, fn($a, $b) => [$a['priority'], $b['available']] <=> [$b['priority'], $a['available']]);

        return $plan;
    }
}
