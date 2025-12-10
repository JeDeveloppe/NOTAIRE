<?php

namespace App\Service;

use App\Entity\Person;
use App\Entity\User;
use App\Entity\Act; 
use App\Repository\PersonRepository;
use App\Repository\ActRepository; 
use App\Service\TreeFormatterService; 
use App\Service\ActService; 
use DateTimeImmutable;

class SimulationPlanningService
{
    // Utilise la constante de ActService
    private const ABATEMENT_CYCLE_YEARS = ActService::ABATEMENT_CYCLE_YEARS;

    // Mappage des relations aux codes fiscaux
    private const RELATION_KEY_MAP = [
        'Parent' => 'parent_enfant',
        'Enfant' => 'parent_enfant',
        'Frère/Sœur' => 'frere_soeur',
        'Grand-Parent' => 'grand_parent_petit_enfant',
        'Petit-Enfant' => 'grand_parent_petit_enfant',
        // Ajout d'autres relations si nécessaire
    ];
    
    // Les codes d'abattement pour la planification des opportunités
    private const FISCAL_ACTS_TO_PLAN = [
        'Abattement_Classique',
        'Don_Sarkozy_Cumulable',
    ];

    private array $abatementAmountsInCents;

    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly ActRepository $actRepository, 
        private readonly ActService $actService, 
        private readonly TreeFormatterService $treeFormatterService, 
        array $abatementAmountsInCents 
    ) {
        $this->abatementAmountsInCents = $abatementAmountsInCents;
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
        // Limite de la planification à 75 ans
        $endYear = $currentYear + 75; 

        $plan = [ 'opportunities' => [], 'people' => [], 'future_actions' => [] ];

        $allPeople = $this->personRepository->findBy(['owner' => $user]);
        $nonDeceasedPeople = [];

        foreach ($allPeople as $person) {
            // Ne traiter que les personnes non décédées et encore vivantes à l'année courante (basé sur date de décès simulée si applicable)
            if ($person->getDateOfDeath() === null && $this->isPersonAliveInYear($person, $currentYear)) {
                 $nonDeceasedPeople[] = $person;
            }
            $plan['people'][$person->getId()] = [
                'name' => $person->getFirstName() . ' ' . $person->getLastName(),
                // Les événements de vie (âge, décès simulé) sont utiles pour la vue
                'events' => $this->getPersonLifeEvents($person, $startDate)
            ];
        }
        $people = $nonDeceasedPeople; 

        $processedPairs = [];

        // --- ÉTAPE 1: Analyse par Paire Donateur/Bénéficiaire ---
        foreach ($people as $donor) {
            foreach ($people as $beneficiary) {
                if ($donor->getId() === $beneficiary->getId()) {
                    continue; 
                }
                
                // Gestion des paires traitées (bidirectionnel)
                $pairKey = $donor->getId() . '_' . $beneficiary->getId();
                $processedPairs[$pairKey] = true; // On marque comme traité, même si bidirectionnel n'est pas utilisé ici
                
                $linkType = $this->determineFiscalRelationship($donor, $beneficiary); 
                
                if ($linkType && isset($this->abatementAmountsInCents[$linkType])) {
                    
                    $maxAbatementClassique = $this->abatementAmountsInCents[$linkType]; 
                    
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
                    if ($this->isSarkozyRelationship($donor, $beneficiary, $linkType)) {
                        $maxAbatementSarkozy = $this->abatementAmountsInCents['don_sarkozy_cumulable'] ?? 0;
                        $pairData['fiscal_acts']['Don_Sarkozy_Cumulable'] = 
                            $this->processSarkozyAbatement($donor, $beneficiary, $linkType, $maxAbatementSarkozy, $currentYear, $plan['future_actions']);
                    }
                    
                    $plan['opportunities'][$pairKey] = $pairData;
                }
            } 
        } 
        
        // --- ÉTAPE 2: Tri des actions futures par année ---
        // Le tri est fait à la fin pour les actions, l'opportunité est triée dans Twig
        ksort($plan['future_actions']);
        $allFutureActions = [];
        foreach ($plan['future_actions'] as $year => $actions) {
            if ($year === 'ALERTS') continue; // Les alertes sont traitées séparément
            $allFutureActions = array_merge($allFutureActions, $actions);
        }
        $plan['future_actions'] = $allFutureActions;


        return $plan;
    }
    
    // =======================================================
    // FONCTIONS DE PROCESSUS D'ACTES
    // =======================================================
    
    /**
     * Traite la logique pour l'abattement classique (15 ans par prescription individuelle).
     */
    private function processClassicalAbatement(
        Person $donor, Person $beneficiary, string $linkType, int $maxAbatementClassique, int $currentYear, int $endYear, array &$futureActions
    ): array
    {
        $data = [
            'max_amount' => $maxAbatementClassique,
            'current_status' => [
                'available_now' => 0,
                'consumed' => 0,
                'next_full_reset_year' => $currentYear + self::ABATEMENT_CYCLE_YEARS, // Valeur par défaut
            ],
            'past_acts' => [], // Actes non prescrits utilisés pour reconstitutions futures
            'future_plans' => [], // Planification de la reconstitution
        ];
        
        // 1. Calcul de la consommation actuelle (Abattement Classique)
        // Date de prescription : tous les actes antérieurs à cette date sont prescrits
        $prescriptionDate = new DateTimeImmutable('-' . self::ABATEMENT_CYCLE_YEARS . ' years');
        
        $consumedAbatementInWindow = $this->actRepository->getConsumedAbatementForCycle(
            $donor, $beneficiary, $prescriptionDate
        );
        
        $availableNow = max(0, $maxAbatementClassique - $consumedAbatementInWindow);
        
        $data['current_status']['consumed'] = $consumedAbatementInWindow;
        $data['current_status']['available_now'] = $availableNow;
        
        // 2. Planification T0 si disponible
        if ($availableNow > 0) {
            $this->addFutureAction($futureActions, $donor, $beneficiary, $linkType, $availableNow, 'Abattement_Classique_T0', $currentYear, 'Opportunité immédiate (solde restant)');
        }
        
        // 3. Planification des futures reconstitutions par prescription des actes passés
        // Récupérer tous les actes (pas seulement le dernier) qui ne sont pas encore prescrits
        $actsToPrescribe = $this->actRepository->findNonPrescribedActs($donor, $beneficiary, $prescriptionDate);
        
        foreach ($actsToPrescribe as $act) {
            /** @var Act $act */
            $consumedByAct = $this->actService->calculateConsumedAbatement($act->getTypeOfAct(), $act->getValue()); 
            
            if ($consumedByAct > 0) {
                // Année où cet acte devient prescrit (Date de l'acte + 15 ans)
                $prescriptionYear = (int) $act->getDateOfAct()->modify('+' . self::ABATEMENT_CYCLE_YEARS . ' years')->format('Y');

                // Si la prescription est dans le futur (et avant la limite de 75 ans)
                if ($prescriptionYear > $currentYear && $prescriptionYear <= $endYear) {
                    
                    $detail = sprintf("Reconstitution de %s € de l'acte du %s.", 
                        number_format($consumedByAct / 100, 0, ',', ' '), $act->getDateOfAct()->format('d/m/Y')
                    );
                    
                    // Ajout dans la liste des actions futures
                    $this->addFutureAction($futureActions, $donor, $beneficiary, $linkType, $consumedByAct, 'Abattement_Classique_Reconstitution', $prescriptionYear, $detail);

                    // Ajout dans la structure de l'opportunité pour l'affichage de l'historique
                    $data['future_plans'][$prescriptionYear][] = [
                        'amount' => $consumedByAct,
                        'source_act_date' => $act->getDateOfAct()->format('Y-m-d'),
                        'reconstitution_year' => $prescriptionYear
                    ];
                }

                // Ajout à l'historique de la paire (pour la liste "Historique des Consommations" du template)
                $data['past_acts'][] = [
                    'amount' => $act->getValue(), // Valeur totale du don
                    'consumed' => $consumedByAct, // Part qui a consommé l'abattement
                    'date' => $act->getDateOfAct()->format('Y-m-d'),
                    'is_prescribed' => $prescriptionYear <= $currentYear
                ];
            }
        }
        
        // Calcul du prochain "reset complet" théorique (pour information)
        // C'est l'année de prescription de l'acte le plus récent non prescrit.
        $lastAct = $this->actRepository->findLatestActForPair($donor->getId(), $beneficiary->getId());
        if ($lastAct && $consumedAbatementInWindow > 0) {
            $data['current_status']['next_full_reset_year'] = (int) $lastAct->getDateOfAct()->modify('+' . self::ABATEMENT_CYCLE_YEARS . ' years')->format('Y');
        } else {
             // Si aucun acte, le reset complet est immédiat (si T0 est utilisé) ou lointain (si rien n'est fait)
             $data['current_status']['next_full_reset_year'] = $availableNow > 0 ? $currentYear + self::ABATEMENT_CYCLE_YEARS : 9999;
        }
        
        return $data;
    }
    
    /**
     * Traite la logique pour le Don Sarkozy (à vie, non cyclique).
     */
    private function processSarkozyAbatement(
        Person $donor, Person $beneficiary, string $linkType, int $maxAbatementSarkozy, int $currentYear, array &$futureActions
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
            'future_plans' => [], // N/A pour Sarkozy
        ];
        
        $donorAge = $this->getAgeInYear($donor, $currentYear);
        $beneficiaryAge = $this->getAgeInYear($beneficiary, $currentYear);
        
        $isEligible = $this->isSarkozyEligible($donor, $beneficiary, $linkType, $donorAge) && $beneficiaryAge >= 18;
        
        $data['current_status']['is_eligible'] = $isEligible;
        
        // 1. Calcul de la consommation totale
        $sarkozyConsumed = $this->actService->getSarkozyConsumedAmount($donor, $beneficiary);
        $abattementSarkozyRestant = max(0, $maxAbatementSarkozy - $sarkozyConsumed);
        
        $data['current_status']['consumed'] = $sarkozyConsumed;
        $data['current_status']['available_now'] = $abattementSarkozyRestant;
        
        // 2. Récupération des actes passés Sarkozy
        $sarkozyActs = $this->actRepository->findSarkozyActs($donor, $beneficiary);
        foreach ($sarkozyActs as $act) {
             $data['past_acts'][] = [
                'amount' => $act->getValue(),
                'consumed' => $act->getValue(), // Consommation = Valeur pour cet acte
                'date' => $act->getDateOfAct()->format('Y-m-d'),
                'is_prescribed' => false // Jamais prescrit
            ];
        }

        // 3. Planification T0 si disponible et éligible
        if ($abattementSarkozyRestant > 0 && $isEligible) {
            $this->addFutureAction($futureActions, $donor, $beneficiary, $linkType, $abattementSarkozyRestant, 'Don_Sarkozy_Cumulable_T0', $currentYear, 'Solde disponible de l\'enveloppe à vie.');
            
            // Ajout d'une alerte si le donateur approche des 80 ans
            $yearTurns80 = $this->getYearTurns80($donor);
            // Si le donateur n'a pas encore 80 ans et est planifié pour l'avenir
            if ($donorAge < 80 && $yearTurns80 > $currentYear) {
                $this->addImperativeAlert($futureActions, $donor, $yearTurns80, 'Don_Sarkozy_80_ans_limite');
            }
        }
        
        return $data;
    }
    
    // =======================================================
    // FONCTIONS D'AIDE ET DE LOGIQUE FISCALE
    // =======================================================

    private function determineFiscalRelationship(Person $donor, Person $beneficiary): ?string
    {
        // Utilise le service de l'arbre pour déterminer la relation sémantique
        $relationship = $this->treeFormatterService->getRelationship($donor, $beneficiary);
        
        if (isset(self::RELATION_KEY_MAP[$relationship])) {
            return self::RELATION_KEY_MAP[$relationship]; 
        } 
        
        // Gérer spécifiquement les relations bi-directionnelles
        if ($relationship === 'Parent' || $relationship === 'Enfant') {
            return self::RELATION_KEY_MAP['Parent']; 
        } 
        
        if ($relationship === 'Grand-Parent' || $relationship === 'Petit-Enfant') {
             return self::RELATION_KEY_MAP['Petit-Enfant']; 
        }

        return null;
    }
    
    private function isSarkozyRelationship(Person $donor, Person $beneficiary, string $linkType): bool
    {
        // Don Sarkozy s'applique uniquement aux liens descendants (Parent/Enfant ou Grand-Parent/Petit-Enfant)
        if ($linkType === 'parent_enfant') {
            return $beneficiary->getParents()->contains($donor); // Donateur est bien le parent
        }
        
        if ($linkType === 'grand_parent_petit_enfant') {
            return $this->isGrandParentToGrandChild($donor, $beneficiary); // Donateur est bien le grand-parent
        }
        
        return false;
    }

    private function isSarkozyEligible(Person $donor, Person $beneficiary, string $linkType, ?int $donorAge): bool
    {
        // 1. Vérification de l'âge du Donateur (< 80 ans)
        if ($donorAge === null || $donorAge >= 80) {
            return false;
        }
        
        // 2. Vérification de la relation descendante
        return $this->isSarkozyRelationship($donor, $beneficiary, $linkType);
    }
    
    private function isGrandParentToGrandChild(Person $donor, Person $beneficiary): bool
    {
        // Logique pour vérifier si le donneur est un grand-parent du bénéficiaire
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
        // Si DateOfDeath est définie et l'année est après, la personne est décédée
        if ($person->getDateOfDeath() !== null) {
            $deathYear = (int) $person->getDateOfDeath()->format('Y');
            return $year <= $deathYear;
        }
        
        // Utilisation d'une limite d'âge estimée (ex: 100 ans)
        $ageLimitYear = $this->getYearTurns80($person) + 20; // Utiliser une limite haute si date de décès non connue
        return $year < $ageLimitYear;
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
    
    /**
     * Ajout une action de planification dans la structure future_actions.
     */
    private function addFutureAction(
        array &$actions, Person $donor, Person $beneficiary, string $linkType, int $amount, 
        string $type, int $year, string $detail
    ): void
    {
        // Ne pas planifier si le donateur ou le bénéficiaire est décédé à cette année
        if (!$this->isPersonAliveInYear($donor, $year) || !$this->isPersonAliveInYear($beneficiary, $year)) {
            return;
        }

        $donorAge = $this->getAgeInYear($donor, $year);
        $beneficiaryAge = $this->getAgeInYear($beneficiary, $year);
        
        // Utilisation de l'année comme clé principale pour le tri
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
    
    /**
     * Ajout une alerte impérative (hors cycle classique).
     */
    private function addImperativeAlert(array &$actions, Person $person, int $year, string $type): void
    {
        $name = $person->getFirstName() . ' ' . $person->getLastName();
        
        $limitText = match ($type) {
            'Don_Sarkozy_80_ans_limite' => "Limite d'âge de 80 ans pour le Don Sarkozy (Art. 790 G). L'opportunité sera bloquée après cette date.",
            default => "Alerte Fiscale"
        };
        
        // Ajout à une clé spéciale pour isoler les alertes
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
        // Retourne des événements (âge, etc.) pour la vue
        $birthYear = (int) $person->getDateOfBirth()->format('Y');
        $currentYear = (int) $startDate->format('Y');
        $age = $currentYear - $birthYear;
        $events = ['age' => $age];
        
        // Ajout d'une estimation ou de la date réelle de décès
        if ($person->getDateOfDeath()) {
            $events['death_year'] = (int) $person->getDateOfDeath()->format('Y');
        }
        
        // Ajout de l'âge limite pour Sarkozy
        $events['limit_80_year'] = $this->getYearTurns80($person);

        return $events;
    }
}