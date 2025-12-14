<?php

namespace App\Service;

use App\Entity\Person;
use App\Entity\User;
use App\Entity\Act; 
use App\Repository\FiscalAbatementRuleRepository; 
use App\Repository\PersonRepository;
use App\Repository\ActRepository; 
use App\Service\TreeFormatterService; 
use App\Service\ActService; 
use DateTimeImmutable;

class SimulationPlanningService
{
    private const ABATEMENT_CYCLE_YEARS = ActService::ABATEMENT_CYCLE_YEARS;

    private const RELATION_KEY_MAP = [
        'Parent' => 'parent_enfant',
        'Enfant' => 'parent_enfant',
        'Frère/Sœur' => 'frere_soeur',
        'Grand-Parent' => 'grand_parent_petit_enfant',
        'Petit-Enfant' => 'grand_parent_petit_enfant',
    ];
    
    private const CODE_SARKOZY = 'DON_SARKOZY_CUMULABLE';


    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly ActRepository $actRepository, 
        private readonly ActService $actService, 
        private readonly TreeFormatterService $treeFormatterService, 
        private readonly FiscalAbatementRuleRepository $fiscalAbatementRuleRepository
    ) {
    }

    /**
     * Génère une structure de simulation axée sur les opportunités fiscales par paires.
     * @param User $user Utilisateur propriétaire des personnes.
     * @return array Le plan de simulation détaillé.
     */
    public function getSimulationPlan(User $user): array
    {
        $startDate = new DateTimeImmutable();
        $currentYear = (int) $startDate->format('Y');
        $endYear = $currentYear + 75; 

        $plan = [ 'opportunities' => [], 'people' => [], 'future_actions' => [] ];

        $allPeople = $this->personRepository->findBy(['owner' => $user]);
        $nonDeceasedPeople = [];

        foreach ($allPeople as $person) {
            if ($person->getDateOfDeath() === null && $this->isPersonAliveInYear($person, $currentYear)) {
                 $nonDeceasedPeople[] = $person;
            }
            $plan['people'][$person->getId()] = [
                'name' => $person->getFirstName() . ' ' . $person->getLastName(),
                'events' => $this->getPersonLifeEvents($person, $startDate)
            ];
        }
        $people = $nonDeceasedPeople; 

        $processedPairs = [];

        // Pré-charge la règle Sarkozy
        $sarkozyRule = $this->fiscalAbatementRuleRepository->findOneByCode(self::CODE_SARKOZY);


        // --- ÉTAPE 1: Analyse par Paire Donateur/Bénéficiaire ---
        foreach ($people as $donor) {
            foreach ($people as $beneficiary) {
                if ($donor->getId() === $beneficiary->getId()) {
                    continue; 
                }
                
                $pairKey = $donor->getId() . '_' . $beneficiary->getId();
                $processedPairs[$pairKey] = true;
                
                $linkType = $this->determineFiscalRelationship($donor, $beneficiary); 
                
                // Recherche dynamique de la règle d'abattement classique pour ce lien
                $classiqueRule = $this->fiscalAbatementRuleRepository->findClassiqueByLinkType($linkType);
                
                if ($classiqueRule) {
                    
                    $maxAbatementClassique = $classiqueRule->getAmountInCents(); 
                    
                    $pairData = [
                        'donor_id' => $donor->getId(),
                        'beneficiary_id' => $beneficiary->getId(),
                        'donor_name' => $donor->getFirstName() . ' ' . $donor->getLastName(),
                        'beneficiary_name' => $beneficiary->getFirstName() . ' ' . $beneficiary->getLastName(),
                        'relationship_type' => $linkType,
                        'fiscal_acts' => [],
                    ];

                    // --- 1. Gestion de l'Abattement Classique (15 ans) ---
                    $pairData['fiscal_acts']['Abattement_Classique'] = 
                        $this->processClassicalAbatement($donor, $beneficiary, $linkType, $maxAbatementClassique, $currentYear, $endYear, $plan['future_actions']);
                    
                    // --- 2. Gestion du Don Sarkozy (à vie) ---
                    if ($this->isSarkozyRelationship($donor, $beneficiary, $linkType) && $sarkozyRule) {
                        
                        $maxAbatementSarkozy = $sarkozyRule->getAmountInCents();
                        
                        // Utilisation des nouveaux Getters
                        $pairData['fiscal_acts']['Don_Sarkozy_Cumulable'] = 
                            $this->processSarkozyAbatement(
                                $donor, $beneficiary, $linkType, 
                                $maxAbatementSarkozy, 
                                $sarkozyRule->getMaxDonorAge(), 
                                $sarkozyRule->getMinBeneficiaryAge(), 
                                $currentYear, 
                                $plan['future_actions']
                            );
                    }
                    
                    $plan['opportunities'][$pairKey] = $pairData;
                }
            } 
        } 
        
        // --- ÉTAPE 2: Tri des actions futures par année ---
        ksort($plan['future_actions']);
        $allFutureActions = [];
        foreach ($plan['future_actions'] as $year => $actions) {
            if ($year === 'ALERTS') continue;
            $allFutureActions = array_merge($allFutureActions, $actions);
        }
        $plan['future_actions'] = $allFutureActions;


        return $plan;
    }
    
    // =======================================================
    // FONCTIONS DE PROCESSUS D'ACTES
    // =======================================================
    
    private function processClassicalAbatement(
        Person $donor, Person $beneficiary, string $linkType, int $maxAbatementClassique, int $currentYear, int $endYear, array &$futureActions
    ): array
    {
        $data = [
            'max_amount' => $maxAbatementClassique,
            'current_status' => [
                'available_now' => 0,
                'consumed' => 0,
                'next_full_reset_year' => $currentYear + self::ABATEMENT_CYCLE_YEARS,
            ],
            'past_acts' => [],
            'future_plans' => [],
        ];
        
        $prescriptionDate = new DateTimeImmutable('-' . self::ABATEMENT_CYCLE_YEARS . ' years');
        
        $consumedAbatementInWindow = $this->actRepository->getConsumedAbatementForCycle(
            $donor, $beneficiary, $prescriptionDate
        );
        
        $availableNow = max(0, $maxAbatementClassique - $consumedAbatementInWindow);
        
        $data['current_status']['consumed'] = $consumedAbatementInWindow;
        $data['current_status']['available_now'] = $availableNow;
        
        if ($availableNow > 0) {
            $this->addFutureAction($futureActions, $donor, $beneficiary, $linkType, $availableNow, 'Abattement_Classique_T0', $currentYear, 'Opportunité immédiate (solde restant)');
        }
        
        $actsToPrescribe = $this->actRepository->findNonPrescribedActs($donor, $beneficiary, $prescriptionDate);
        
        foreach ($actsToPrescribe as $act) {
            /** @var Act $act */
            $consumedByAct = $this->actService->calculateConsumedAbatement($act->getTypeOfAct(), $act->getValue()); 
            
            if ($consumedByAct > 0) {
                $prescriptionYear = (int) $act->getDateOfAct()->modify('+' . self::ABATEMENT_CYCLE_YEARS . ' years')->format('Y');

                if ($prescriptionYear > $currentYear && $prescriptionYear <= $endYear) {
                    
                    $detail = sprintf("Reconstitution de %s € de l'acte du %s.", 
                        number_format($consumedByAct / 100, 0, ',', ' '), $act->getDateOfAct()->format('d/m/Y')
                    );
                    
                    $this->addFutureAction($futureActions, $donor, $beneficiary, $linkType, $consumedByAct, 'Abattement_Classique_Reconstitution', $prescriptionYear, $detail);

                    $data['future_plans'][$prescriptionYear][] = [
                        'amount' => $consumedByAct,
                        'source_act_date' => $act->getDateOfAct()->format('Y-m-d'),
                        'reconstitution_year' => $prescriptionYear
                    ];
                }

                $data['past_acts'][] = [
                    'amount' => $act->getValue(),
                    'consumed' => $consumedByAct,
                    'date' => $act->getDateOfAct()->format('Y-m-d'),
                    'is_prescribed' => $prescriptionYear <= $currentYear
                ];
            }
        }
        
        $lastAct = $this->actRepository->findLatestActForPair($donor->getId(), $beneficiary->getId());
        if ($lastAct && $consumedAbatementInWindow > 0) {
            $data['current_status']['next_full_reset_year'] = (int) $lastAct->getDateOfAct()->modify('+' . self::ABATEMENT_CYCLE_YEARS . ' years')->format('Y');
        } else {
             $data['current_status']['next_full_reset_year'] = $availableNow > 0 ? $currentYear + self::ABATEMENT_CYCLE_YEARS : 9999;
        }
        
        return $data;
    }
    
    private function processSarkozyAbatement(
        Person $donor, Person $beneficiary, string $linkType, 
        int $maxAbatementSarkozy, 
        int $maxDonorAge, 
        int $minBeneficiaryAge,
        int $currentYear, 
        array &$futureActions
    ): array
    {
        $data = [
            'max_amount' => $maxAbatementSarkozy,
            'current_status' => [
                'available_now' => 0,
                'consumed' => 0,
                'is_eligible' => false,
            ],
            'past_acts' => [],
            'future_plans' => [],
        ];
        
        $donorAge = $this->getAgeInYear($donor, $currentYear);
        $beneficiaryAge = $this->getAgeInYear($beneficiary, $currentYear);
        
        // La fonction isSarkozyEligible utilise maintenant maxDonorAge
        $donorEligible = $this->isSarkozyEligible($donor, $beneficiary, $linkType, $donorAge, $maxDonorAge); 
        
        // Année où le bénéficiaire atteindra l'âge minimum requis (18 ans)
        $yearTurnsMinAge = ($beneficiary->getDateOfBirth()) 
            ? (int) $beneficiary->getDateOfBirth()->format('Y') + $minBeneficiaryAge 
            : 9999;

        $isEligibleNow = $donorEligible && $beneficiaryAge >= $minBeneficiaryAge;
        $data['current_status']['is_eligible'] = $isEligibleNow;

        $sarkozyConsumed = $this->actService->getSarkozyConsumedAmount($donor, $beneficiary);
        $abattementSarkozyRestant = max(0, $maxAbatementSarkozy - $sarkozyConsumed);
        
        $data['current_status']['consumed'] = $sarkozyConsumed;
        $data['current_status']['available_now'] = $isEligibleNow ? $abattementSarkozyRestant : 0;
        
        $sarkozyActs = $this->actRepository->findSarkozyActs($donor, $beneficiary);
        foreach ($sarkozyActs as $act) {
             $data['past_acts'][] = [
                'amount' => $act->getValue(),
                'consumed' => $act->getValue(),
                'date' => $act->getDateOfAct()->format('Y-m-d'),
                'is_prescribed' => false
            ];
        }

        if ($abattementSarkozyRestant > 0) {
            
            if ($isEligibleNow) {
                $this->addFutureAction($futureActions, $donor, $beneficiary, $linkType, $abattementSarkozyRestant, 'Don_Sarkozy_Cumulable_T0', $currentYear, 'Solde disponible de l\'enveloppe à vie.');
            } 
            
            elseif ($donorEligible && $beneficiaryAge < $minBeneficiaryAge) {
                
                if ($yearTurnsMinAge > $currentYear) {
                     $this->addFutureAction($futureActions, $donor, $beneficiary, $linkType, $abattementSarkozyRestant, 'Don_Sarkozy_Cumulable_T0', $yearTurnsMinAge, 'Solde disponible de l\'enveloppe à vie (Atteinte 18 ans).');
                }
            }
            
            if ($donorEligible) {
                $yearTurnsMaxAge = $this->getYearTurnsAge($donor, $maxDonorAge);
                if ($donorAge < $maxDonorAge && $yearTurnsMaxAge > $currentYear) {
                    
                    if ($yearTurnsMinAge > $yearTurnsMaxAge) {
                         $this->addImperativeAlert($futureActions, $donor, $yearTurnsMaxAge, 'Don_Sarkozy_80_ans_limite');
                    }
                }
            }
        }
        
        return $data;
    }
    
    // =======================================================
    // FONCTIONS D'AIDE ET DE LOGIQUE FISCALE
    // =======================================================

    private function determineFiscalRelationship(Person $donor, Person $beneficiary): ?string
    {
        $relationship = $this->treeFormatterService->getRelationship($donor, $beneficiary);
        
        if (isset(self::RELATION_KEY_MAP[$relationship])) {
            return self::RELATION_KEY_MAP[$relationship]; 
        }

        return null;
    }
    
    private function isSarkozyRelationship(Person $donor, Person $beneficiary, string $linkType): bool
    {
        if ($linkType === 'parent_enfant') {
            return $beneficiary->getParents()->contains($donor);
        }
        
        if ($linkType === 'grand_parent_petit_enfant') {
            return $this->isGrandParentToGrandChild($donor, $beneficiary);
        }
        
        return false;
    }

    private function isSarkozyEligible(Person $donor, Person $beneficiary, string $linkType, ?int $donorAge, int $maxDonorAge): bool
    {
        // 1. Vérification de l'âge du Donateur (utilise maxDonorAge lu en BDD)
        if ($donorAge === null || $donorAge >= $maxDonorAge) {
            return false;
        }
        
        // 2. Vérification de la relation descendante
        return $this->isSarkozyRelationship($donor, $beneficiary, $linkType);
    }
    
    private function isGrandParentToGrandChild(Person $donor, Person $beneficiary): bool
    {
        foreach ($beneficiary->getParents() as $parent) {
            foreach ($parent->getParents() as $grandParent) {
                if ($grandParent->getId() === $donor->getId()) {
                    return true;
                }
            }
        }
        return false;
    }
    
    private function isPersonAliveInYear(Person $person, int $year): bool
    {
        if ($person->getDateOfDeath() !== null) {
            $deathYear = (int) $person->getDateOfDeath()->format('Y');
            return $year <= $deathYear;
        }
        
        $ageLimitYear = $this->getYearTurnsAge($person, 100); 
        return $year < $ageLimitYear;
    }
    
    private function getAgeInYear(Person $person, int $year): ?int
    {
        if ($person->getDateOfBirth()) {
            return $year - (int) $person->getDateOfBirth()->format('Y');
        }
        return null;
    }
    
    private function getYearTurnsAge(Person $person, int $age): int
    {
        if ($person->getDateOfBirth()) {
            return (int) $person->getDateOfBirth()->format('Y') + $age;
        }
        return 9999;
    }
    
    private function addFutureAction(
        array &$actions, Person $donor, Person $beneficiary, string $linkType, int $amount, 
        string $type, int $year, string $detail
    ): void
    {
        if (!$this->isPersonAliveInYear($donor, $year) || !$this->isPersonAliveInYear($beneficiary, $year)) {
            return;
        }

        $donorAge = $this->getAgeInYear($donor, $year);
        $beneficiaryAge = $this->getAgeInYear($beneficiary, $year);
        
        if (!isset($actions[$year])) {
            $actions[$year] = [];
        }
        
        $actions[$year][] = [
            'donor_id' => $donor->getId(), 'beneficiary_id' => $beneficiary->getId(),
            'amount' => $amount, 'type' => $type, 'year' => $year,
            'detail' => sprintf("%s de %s € : %s", 
                $this->getActLabel($type), number_format($amount / 100, 0, ',', ' '), $detail
            ),
            'donor_age' => $donorAge, 'beneficiary_age' => $beneficiaryAge,
            'is_imperative' => false,
        ];
    }
    
    private function addImperativeAlert(array &$actions, Person $person, int $year, string $type): void
    {
        $name = $person->getFirstName() . ' ' . $person->getLastName();
        
        $limitText = match ($type) {
            'Don_Sarkozy_80_ans_limite' => "Limite d'âge de 80 ans pour le Don Sarkozy (Art. 790 G). L'opportunité sera bloquée après cette date.",
            default => "Alerte Fiscale"
        };
        
        if (!isset($actions['ALERTS'])) {
            $actions['ALERTS'] = [];
        }
        
        $actions['ALERTS'][] = [
            'donor_id' => $person->getId(), 
            'year' => $year, 
            'type' => $type, 
            'detail' => sprintf("Alerte %s: %s atteint l'âge de 80 ans en %d. %s", 
                'Donateur', $name, $year, $limitText
            ),
            'is_imperative' => true 
        ];
    }
    
    private function getActLabel(string $type): string
    {
        return match($type) {
            'Abattement_Classique_T0' => 'Abattement Classique disponible',
            'Abattement_Classique_Reconstitution' => 'Reconstitution d\'Abattement Classique',
            'Don_Sarkozy_Cumulable_T0' => 'Don Sarkozy (Argent) disponible',
            default => 'Action Fiscale'
        };
    }
    
    private function getPersonLifeEvents(Person $person, DateTimeImmutable $startDate): array
    {
        $birthYear = (int) $person->getDateOfBirth()->format('Y');
        $currentYear = (int) $startDate->format('Y');
        $age = $currentYear - $birthYear;
        $events = ['age' => $age];
        
        if ($person->getDateOfDeath()) {
            $events['death_year'] = (int) $person->getDateOfDeath()->format('Y');
        }
        
        $events['limit_80_year'] = $this->getYearTurnsAge($person, 80); 

        return $events;
    }
}