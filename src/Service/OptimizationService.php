<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Donation;
use App\Entity\DonationRule;
use App\Repository\RelationshipRepository;
use DateTime;
use DateTimeImmutable;

class OptimizationService
{
    public function __construct(
        private RelationshipRepository $relationshipRepository,
        private DonationService $donationService,
        private TaxBracketService $taxService
    ) {}

    public function getDonationAnalyses(User $user): array
    {
        $people = $user->getPeople();
        $analysis = [
            'expired_periods' => [], // Abattements passés avec reliquat non utilisé
            'active_periods' => [], // Abattements en cours (cycle de 15 ans actif)
            'never_used' => [], // NOUVEAU : Droits jamais activés (perte de chance)
            'total_missed' => 0 // Somme des reliquats + droits jamais activés
        ];

        // 1. Analyse des dons existants (Logique existante)
        foreach ($people as $person) {
            foreach ($person->getDonationsGiven() as $donation) {
                $this->processSingleDonation($donation, $analysis);
            }
        }

        // 2. Analyse des relations "vierges" (Opportunités totalement manquées)
        foreach ($people as $donor) {
            foreach ($people as $beneficiary) {
                if ($donor === $beneficiary) continue;

                // On vérifie s'il existe au moins un don entre ces deux personnes
                $donations = $this->donationService->getDonationsBetween($donor, $beneficiary);

                if (empty($donations)) {
                    // Si aucun don n'a été fait, on simule ce qui "aurait pu être purgé" il y a 15 ans
                    $simulation = $this->donationService->simulateMaxTaxFree($donor, $beneficiary);
                    foreach ($simulation['rules'] as $rule) {
                        if ($rule['is_valid'] && $rule['available'] > 0) {
                            $analysis['never_used'][] = [
                                'donor' => $donor->getFirstname(),
                                'beneficiary' => $beneficiary->getFirstname(),
                                'label' => $rule['label'],
                                'amount' => $rule['available']
                            ];
                            $analysis['total_missed'] += $rule['available'];
                        }
                    }
                }
            }
        }

        // Tris chronologiques
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

    // TaxOptimizationService.php

    public function getGlobalFamilyPlan(User $user, ?\DateTimeInterface $referenceDate = null): array
    {
        $referenceDate = $referenceDate ?? new \DateTimeImmutable();
        $people = $user->getPeople();
        $plan = [];

        // Liste des codes autorisés (Flux descendant uniquement)
        $allowedCodes = ['ENFANT', 'PETIT_ENFANT', 'ARRIERE_PETIT_ENFANT'];

        foreach ($people as $donor) {
            foreach ($people as $beneficiary) {
                if ($donor === $beneficiary) continue;

                $simulation = $this->donationService->simulateMaxTaxFree($donor, $beneficiary, $referenceDate);

                // FILTRE : On ne garde que la ligne descendante
                if (!in_array($simulation['relationship_code'], $allowedCodes)) {
                    continue;
                }

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

        usort($plan, fn($a, $b) => [$a['priority'], $b['available']] <=> [$b['priority'], $a['available']]);

        return $plan;
    }

    public function getSimulationDatas(?string $dateParam = null, User $user)
    {
        $data = [];

        $data['referenceDate'] = $dateParam ? new \DateTimeImmutable($dateParam) : new \DateTimeImmutable();

        $data['analysis'] = $this->getDonationAnalyses($user);
        $data['familyPlan'] = $this->getGlobalFamilyPlan($user, $data['referenceDate']);
        
        $data['totalAvailable'] = array_sum(array_column($data['familyPlan'], 'available'));

        $totalSaving = 0;
        $familyPlan = $data['familyPlan'];
        foreach ($familyPlan as $item) {
            $totalSaving += $this->taxService->calculateSaving(
                $item['available'], 
                $item['relationship_code']
            );
        }
        $data['totalSaving'] = $totalSaving;

        return $data;
    }
}
