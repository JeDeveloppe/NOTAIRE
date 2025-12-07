<?php

namespace App\Service;

use App\Entity\Person;
use App\Entity\User;
use App\Entity\Act; 
use App\Repository\PersonRepository;
use App\Repository\ActRepository; 
use DateTimeImmutable;

class SimulationPlanningService
{
    // Constante définissant la durée du cycle d'abattement
    private const ABATEMENT_CYCLE_YEARS = 15;

    private array $abatementAmountsInCents;

    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly ActRepository $actRepository, 
        array $abatementAmountsInCents 
    ) {
        $this->abatementAmountsInCents = $abatementAmountsInCents;
    }

    /**
     * Génère une structure de simulation en se basant sur l'état actuel de l'abattement.
     */
    public function getSimulationPlan(User $user): array
    {
        $startDate = new DateTimeImmutable();
        $currentYear = (int) $startDate->format('Y');
        $endYear = $currentYear + 75; // Horizon de simulation

        $plan = [ 'cycles' => [], 'people' => [] ];

        /** @var Person[] $allPeople */
        $allPeople = $this->personRepository->findBy(['owner' => $user]);
        $actions = []; 
        
        // --- ÉTAPE 1: Filtrage et Initialisation ---
        // FILTRE : Ne garder que les personnes SANS date de décès renseignée
        $nonDeceasedPeople = [];

        foreach ($allPeople as $person) {
            
            // Si la date de décès n'est PAS renseignée
            if ($person->getDateOfDeath() === null) { 
                
                // Et que la personne est considérée comme "vivante"
                if ($this->isPersonAliveInYear($person, $currentYear)) {
                     $nonDeceasedPeople[] = $person;
                }
            } 
            
            // Initialisation de la donnée personne (pour l'affichage de l'historique de vie complet)
            $plan['people'][$person->getId()] = [
                'name' => $person->getFirstName() . ' ' . $person->getLastName(),
                'events' => $this->getPersonLifeEvents($person, $startDate)
            ];
        }
        
        $people = $nonDeceasedPeople; 

        // --- ÉTAPE 2: Simulation des Cycles Donateur/Bénéficiaire ---
        foreach ($people as $donor) {
            
            foreach ($people as $beneficiary) {
                if ($donor->getId() === $beneficiary->getId()) 
                {
                    continue;
                }
                
                // Détermination du lien fiscal (parent_enfant, frere_soeur, etc.)
                $linkType = $this->determineFiscalRelationship($donor, $beneficiary); 
                
                // Si la relation a un abattement configuré (ex: 100 000€)
                if ($linkType && isset($this->abatementAmountsInCents[$linkType])) {
                    
                    $maxAbatement = $this->abatementAmountsInCents[$linkType];
                    $abatementSarkozy = $this->abatementAmountsInCents['don_sarkozy_cumulable'] ?? 0;
                    
                    // 1. Récupération des données du cycle actuel
                    $lastAct = $this->actRepository->findLatestActForPair(
                        $donor->getId(), $beneficiary->getId()
                    );
                    
                    $cycleData = $this->getCycleStatus($donor, $beneficiary, $maxAbatement, $lastAct, $currentYear);
                    
                    // ⚠️ CORRECTION DE LA CLÉ DE L'ARRAY UTILISÉE ICI : 'abattementAvailableNow'
                    $abattementAvailableNow = $cycleData['abattementAvailableNow']; 
                    $nextFullResetYear = $cycleData['nextFullResetYear'];
                    $donorAge = $cycleData['donorAge'];
                    $beneficiaryAge = $cycleData['beneficiaryAge'];

                    // --- LOGIQUE 1A : OPPORTUNITÉ IMMÉDIATE - ABATTEMENT CLASSIQUE ---
                    if ($abattementAvailableNow > 0) {
                        
                        $actions[] = $this->createActionEntry(
                            $donor, $beneficiary, $linkType, $abattementAvailableNow, 'Abattement_Classique', $currentYear, $donorAge, $beneficiaryAge
                        );

                        if ($abattementAvailableNow === $maxAbatement) {
                            $nextFullResetYear = $currentYear + self::ABATEMENT_CYCLE_YEARS;
                        }
                    }

                    // --- LOGIQUE 1B : OPPORTUNITÉ IMMÉDIATE - DON SARKOZY (31865€) ---
                    if ($abatementSarkozy > 0 && 
                        in_array($linkType, ['parent_enfant', 'grand_parent_petit_enfant']) && 
                        $donorAge < 80 && $beneficiaryAge >= 18
                    ) {
                        $sarkozyAvailable = $this->isSarkozyAvailableForCycle($donor, $beneficiary, $lastAct, $currentYear);

                        if ($sarkozyAvailable) {
                            $actions[] = $this->createActionEntry(
                                $donor, $beneficiary, $linkType, $abatementSarkozy, 'Don_Sarkozy_Cumulable', $currentYear, $donorAge, $beneficiaryAge
                            );
                        }
                    }

                    // --- LOGIQUE 2 : PLANIFICATION FUTURE (Cycles complets à venir) ---
                    for ($year = $nextFullResetYear; $year <= $endYear; $year += self::ABATEMENT_CYCLE_YEARS) {
                        
                        $donorAgeReset = $this->getAgeInYear($donor, $year);
                        $beneficiaryAgeReset = $this->getAgeInYear($beneficiary, $year);

                        // Arrêter la planification si un des deux est "décédé" (basé sur dateOfDeath uniquement)
                        if (!$this->isPersonAliveInYear($donor, $year) || !$this->isPersonAliveInYear($beneficiary, $year)) {
                            continue;
                        }

                        // Planification Abattement Classique
                        $actions[] = $this->createActionEntry(
                            $donor, $beneficiary, $linkType, $maxAbatement, 'Abattement_Classique', $year, $donorAgeReset, $beneficiaryAgeReset
                        );

                        // Planification Don Sarkozy (si le donateur est encore de moins de 80 ans)
                        if ($donorAgeReset < 80 && $beneficiaryAgeReset >= 18) {
                            if ($abatementSarkozy > 0 && 
                                in_array($linkType, ['parent_enfant', 'grand_parent_petit_enfant'])) 
                            {
                                $actions[] = $this->createActionEntry(
                                    $donor, $beneficiary, $linkType, $abatementSarkozy, 'Don_Sarkozy_Cumulable', $year, $donorAgeReset, $beneficiaryAgeReset
                                );
                            }
                        }
                        
                        // Alerte Impérative 80 ans Don Sarkozy
                        if ($donorAgeReset < 80) {
                            $yearTurns80 = $this->getYearTurns80($donor);
                            if ($yearTurns80 > $year && $yearTurns80 <= $year + self::ABATEMENT_CYCLE_YEARS - 1) {
                                $actions[] = $this->createImperativeActionEntry(
                                    $donor, $yearTurns80, 'Don_Sarkozy_80_ans_limite'
                                );
                            }
                        }
                    }
                }
            } 
        } 

        // --- ÉTAPE 3: Regroupement et Rendu ---
        $plan['cycles'] = $this->groupActionsByDonorAndBeneficiary($actions);

        return $plan;
    }

    // -----------------------------------------------------------------
    // FONCTIONS D'AIDE PRINCIPALES
    // -----------------------------------------------------------------

    /**
     * Détermine la relation familiale et fiscale entre deux personnes (utilisé pour les clés d'abattement).
     */
    private function determineFiscalRelationship(Person $donor, Person $beneficiary): ?string
    {
        // 1. Parent à Enfant
        if ($beneficiary->getParents()->contains($donor)) {
            return 'parent_enfant';
        }
        
        // 2. Grand-Parent à Petit-Enfant
        foreach ($beneficiary->getParents() as $parent) {
            if ($parent->getParents()->contains($donor)) {
                return 'grand_parent_petit_enfant';
            }
        }
        
        // 3. Frère / Sœur (Vérification d'un parent commun)
        if ($donor->getParents()->count() > 0 && $beneficiary->getParents()->count() > 0) {
            
            $donorParentIds = $donor->getParents()->map(fn($p) => $p->getId())->toArray();
            $beneficiaryParentIds = $beneficiary->getParents()->map(fn($p) => $p->getId())->toArray();

            if (array_intersect($donorParentIds, $beneficiaryParentIds)) {
                return 'frere_soeur'; 
            }
        }

        // 4. Autres relations (null)
        return null;
    }

    /**
     * Récupère la dernière année de vie (réelle) ou retourne 9999 si aucune date n'est renseignée (sans limite de 90 ans).
     */
    private function getSimulatedLastYear(Person $person): int
    {
        if ($person->getDateOfDeath()) {
            return (int) $person->getDateOfDeath()->format('Y');
        }
        // Pas de date de décès réelle = pas de fin de simulation
        return 9999; 
    }

    /**
     * Vérifie si la personne est "vivante" dans l'année donnée (si l'année est inférieure ou égale à l'année de décès réelle).
     */
    private function isPersonAliveInYear(Person $person, int $year): bool
    {
        $lastYear = $this->getSimulatedLastYear($person);
        return $lastYear >= $year;
    }

    // -----------------------------------------------------------------
    // FONCTIONS D'AIDE SECONDAIRES (Gestion des cycles, Âges, Entrées)
    // -----------------------------------------------------------------

    private function getCycleStatus(Person $donor, Person $beneficiary, int $maxAbatement, ?Act $lastAct, int $currentYear): array
    {
        $donorAge = $this->getAgeInYear($donor, $currentYear);
        $beneficiaryAge = $this->getAgeInYear($beneficiary, $currentYear);
        $cycleStartDate = $this->getCycleStartDateForAct($lastAct, $currentYear);
        $nextFullResetYear = $currentYear + self::ABATEMENT_CYCLE_YEARS;
        
        if ($lastAct) {
            $lastActYear = (int) $lastAct->getDateOfAct()->format('Y');
            $yearsSinceLastAct = $currentYear - $lastActYear;
            $cyclesPassed = floor($yearsSinceLastAct / self::ABATEMENT_CYCLE_YEARS);
            $nextFullResetYear = $lastActYear + (($cyclesPassed + 1) * self::ABATEMENT_CYCLE_YEARS); 
        }

        $consumedAbatement = $this->actRepository->getConsumedAbatementForCycle(
            $donor, $beneficiary, $cycleStartDate
        );
        $abatementAvailableNow = max(0, $maxAbatement - $consumedAbatement);

        // ⚠️ CORRECTION DE LA CLÉ DE L'ARRAY EN SORTIE : 'abattementAvailableNow'
        return [
            'abattementAvailableNow' => $abatementAvailableNow, 
            'nextFullResetYear' => $nextFullResetYear,
            'donorAge' => $donorAge,
            'beneficiaryAge' => $beneficiaryAge,
            'consumedAbatement' => $consumedAbatement,
        ];
    }
    
    private function getCycleStartDateForAct(?Act $lastAct, int $currentYear): DateTimeImmutable
    {
        if (!$lastAct) {
            return (new DateTimeImmutable())->modify('-' . self::ABATEMENT_CYCLE_YEARS . ' years');
        }

        $lastActYear = (int) $lastAct->getDateOfAct()->format('Y');
        $yearsSinceLastAct = $currentYear - $lastActYear;
        
        $cyclesPassed = floor($yearsSinceLastAct / self::ABATEMENT_CYCLE_YEARS);
        $currentWindowStartYear = $lastActYear + ($cyclesPassed * self::ABATEMENT_CYCLE_YEARS);

        if ($currentWindowStartYear > $currentYear) {
             return (new DateTimeImmutable())->modify('-' . self::ABATEMENT_CYCLE_YEARS . ' years');
        }
        
        return new DateTimeImmutable($currentWindowStartYear . '-01-01');
    }

    private function isSarkozyAvailableForCycle(Person $donor, Person $beneficiary, ?Act $lastAct, int $currentYear): bool
    {
        $cycleStartDate = $this->getCycleStartDateForAct($lastAct, $currentYear);
        $actFoundCount = $this->actRepository->findSarkozyActForCycle(
            $donor, $beneficiary, $cycleStartDate
        );
        return $actFoundCount === 0;
    }
    
    private function getAgeInYear(Person $person, int $year): ?int
    {
        if ($person->getDateOfBirth()) {
            return $year - (int) $person->getDateOfBirth()->format('Y');
        }
        return null;
    }
    
    private function getYearTurns80(Person $person): int
    {
        if ($person->getDateOfBirth()) {
            return (int) $person->getDateOfBirth()->format('Y') + 80;
        }
        return 9999;
    }

    private function groupActionsByDonorAndBeneficiary(array $actions): array
    {
        $cyclesData = [];
        foreach ($actions as $action) {
            $year = $action['year'];
            if (!isset($cyclesData[$year])) {
                $cyclesData[$year] = ['year' => $year, 'actions_grouped' => []];
            }
            $donorId = $action['donor_id'];
            if (!isset($cyclesData[$year]['actions_grouped'][$donorId])) {
                $cyclesData[$year]['actions_grouped'][$donorId] = ['donor_id' => $donorId, 'actions_by_beneficiary' => []];
            }
            $beneficiaryKey = $action['is_imperative'] ? 'IMPERATIVE_ALERT' : $action['beneficiary_id'];
            if (!isset($cyclesData[$year]['actions_grouped'][$donorId]['actions_by_beneficiary'][$beneficiaryKey])) {
                $cyclesData[$year]['actions_grouped'][$donorId]['actions_by_beneficiary'][$beneficiaryKey] = [];
            }
            $cyclesData[$year]['actions_grouped'][$donorId]['actions_by_beneficiary'][$beneficiaryKey][] = $action;
        }
        ksort($cyclesData);
        return array_values($cyclesData);
    }
    
    private function createActionEntry(Person $donor, Person $beneficiary, string $linkType, int $abattement, string $type, int $year, ?int $donorAge = null, ?int $beneficiaryAge = null): array
    {
        $ageDetail = ($donorAge !== null) ? "(Âge: D={$donorAge} ans / B={$beneficiaryAge} ans)" : '';
        $typeLabel = ($type === 'Don_Sarkozy_Cumulable') ? 'Don Sarkozy (Argent)' : 'Abattement Classique';

        return [
            'donor_id' => $donor->getId(), 'donor_name' => $donor->getFirstName(), 'beneficiary_id' => $beneficiary->getId(), 'beneficiary_name' => $beneficiary->getFirstName(),
            'relationship' => $linkType, 'abattement' => $abattement, 'detail' => sprintf("%s de %s€ de %s à %s %s", $typeLabel, number_format($abattement, 0, ',', ' '), $donor->getFirstName(), $beneficiary->getFirstName(), $ageDetail),
            'type' => $type, 'year' => $year, 'donor_age' => $donorAge, 'beneficiary_age' => $beneficiaryAge, 'is_imperative' => false
        ];
    }
    
    private function createImperativeActionEntry(Person $donor, int $limitYear, string $type): array
    {
        $limitText = match ($type) {
            'Don_Sarkozy_80_ans_limite' => "Limite d'âge de 80 ans pour le Don Sarkozy.",
            default => "Limite de don"
        };
        
        return [
            'donor_id' => $donor->getId(), 'donor_name' => $donor->getFirstName(), 'beneficiary_id' => null, 'beneficiary_name' => null,
            'relationship' => 'ALERTE IMPÉRATIVE', 'abattement' => 0,
            'detail' => sprintf("Le Don Sarkozy pour %s est impératif avant le %s, année où il atteint les 80 ans. L'opportunité sera bloquée après cette date.", $donor->getFirstName(), $limitYear),
            'type' => $type, 'year' => $limitYear, 'donor_age' => 80, 'beneficiary_age' => null, 'is_imperative' => true 
        ];
    }

    private function getPersonLifeEvents(Person $person, DateTimeImmutable $startDate): array
    {
        $events = [];
        $name = $person->getFirstName() . ' ' . $person->getLastName();
        
        if ($person->getDateOfBirth()) {
            $events[] = ['date' => $person->getDateOfBirth()->format('Y'), 'type' => 'Naissance', 'detail' => $name . ' naît.', 'className' => 'life-event birth'];
        }
        if ($person->getDateOfDeath()) {
            $events[] = ['date' => $person->getDateOfDeath()->format('Y'), 'type' => 'Décès', 'detail' => $name . ' décède.', 'className' => 'life-event death'];
        }
        
        usort($events, fn($a, $b) => $a['date'] <=> $b['date']);
        return $events;
    }
}