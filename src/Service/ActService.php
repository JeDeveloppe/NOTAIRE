<?php

namespace App\Service;

use App\Entity\Act;
use App\Entity\Person;
use App\Entity\TypeAct;
use App\Entity\FiscalAbatementRule; // <-- NOUVEAU
use App\Repository\ActRepository;
use App\Repository\FiscalAbatementRuleRepository; // <-- NOUVEAU
use Symfony\Bundle\SecurityBundle\Security; 
use DateTimeImmutable;

class ActService
{
    // Constantes des types d'acte (utilisées par l'entité TypeAct)
    public const CODE_SARKOZY = 'SARKOZY'; 
    public const CODE_CLASSIQUE = 'CLASSIQUE'; 
    public const CODE_USUFRUIT = 'USUFRUIT'; 
    public const ABATEMENT_CYCLE_YEARS = 15;

    // Constantes des codes de règles (utilisées par FiscalAbatementRule)
    public const TYPE_ACT_DONATION = 'DONATION';
    public const TYPE_ACT_DON_ARGENT_SEUL = 'DON_ARGENT_SEUL';
    public const CODE_SARKOZY_RULE = 'DON_SARKOZY_CUMULABLE'; // Le code unique de la règle Sarkozy

    private const SECONDS_IN_15_YEARS = 15 * 365.25 * 24 * 60 * 60; 

    public function __construct(
        private readonly ActRepository $actRepository,
        private readonly Security $security,
        private readonly FiscalAbatementRuleRepository $abatementRuleRepository // <-- INJECTION DU REPOSITORY DE RÈGLES
    ) {
    }

    // =======================================================
    // LOGIQUE FISCALE DES ACTES PASSÉS
    // =======================================================

    /**
     * Calcule le montant de l'abattement consommé (en centimes) par un acte donné.
     * Cette méthode est correcte et ne nécessite pas de changement, car elle dépend
     * du CODE du TypeAct et non du montant de l'abattement.
     */
    public function calculateConsumedAbatement(TypeAct $type, int $valueInCents): int
    {
        $typeCode = $type->getCode(); 
        
        switch ($typeCode) {
            case self::CODE_SARKOZY:
                return 0;                 
            case self::CODE_CLASSIQUE:
            case self::CODE_USUFRUIT:
            default:
                return $valueInCents;
        }
    }
    
    /**
     * Détermine si un acte de donation est prescrit (a plus de 15 ans).
     */
    public function isActPrescribed(Act $act, int $currentTime): bool
    {
        $dateOfActTimestamp = $act->getDateOfAct()->getTimestamp();
        $intervalSeconds = $currentTime - $dateOfActTimestamp;
        
        return $intervalSeconds >= self::SECONDS_IN_15_YEARS;
    }

    /**
     * Trouve l'acte de donation le plus récent entre un donateur et un bénéficiaire.
     */
    public function findLatestActForPair(int $donorId, int $beneficiaryId): ?Act
    {
        return $this->actRepository->findLatestActForPair($donorId, $beneficiaryId);
    }

    // =======================================================
    // LOGIQUE DE CYCLE FISCAL 
    // =======================================================
    
    /**
     * Récupère la règle d'abattement classique pour un lien donné (parent_enfant, etc.).
     */
    public function getClassiqueAbatementRule(string $linkType): ?FiscalAbatementRule
    {
        return $this->abatementRuleRepository->findClassiqueByLinkType($linkType);
    }

    /**
     * Détermine le statut du cycle d'abattement (point de départ, abattement consommé)
     * pour une paire Donateur/Bénéficiaire donnée.
     * @param string $linkType Le type de lien entre les deux personnes.
     */
    public function getCycleStatus(Person $donor, Person $beneficiary, string $linkType, ?Act $lastAct, int $currentYear): array
    {
        // 1. Récupérer l'abattement MAXIMAL depuis la BDD
        $rule = $this->getClassiqueAbatementRule($linkType);
        $maxAbatement = $rule ? $rule->getAmountInCents() : 0;
        
        // Si aucune règle trouvée (Tiers, par exemple, qui n'a pas été créé), on retourne 0
        if ($maxAbatement === 0) {
            return [
                'abattementAvailableNow' => 0, 
                'nextFullResetYear' => $currentYear, // N'a pas de cycle
                'consumedAbatement' => 0,
            ];
        }

        // 2. Détermination de la date de début du cycle actuel
        $cycleStartDate = $this->getCycleStartDateForAct($lastAct, $currentYear);
        
        // 3. Calcul de l'année du prochain reset complet (15 ans depuis le dernier Act)
        // La logique est conservée car elle est basée sur les 15 ans (ABATEMENT_CYCLE_YEARS)
        $nextFullResetYear = $currentYear + self::ABATEMENT_CYCLE_YEARS;
        
        if ($lastAct) {
            $lastActYear = (int) $lastAct->getDateOfAct()->format('Y');
            $yearsSinceLastAct = $currentYear - $lastActYear;
            $cyclesPassed = floor($yearsSinceLastAct / self::ABATEMENT_CYCLE_YEARS);
            $nextFullResetYear = $lastActYear + (($cyclesPassed + 1) * self::ABATEMENT_CYCLE_YEARS); 
        }

        // 4. Calcul de l'abattement consommé DANS la fenêtre actuelle
        $consumedAbatement = $this->actRepository->getConsumedAbatementForCycle(
            $donor, $beneficiary, $cycleStartDate
        );
        
        // 5. Calcul de l'abattement disponible
        $abatementAvailableNow = max(0, $maxAbatement - $consumedAbatement);

        return [
            'abattementAvailableNow' => $abatementAvailableNow, 
            'nextFullResetYear' => $nextFullResetYear,
            'consumedAbatement' => $consumedAbatement,
        ];
    }
    
    /**
     * Détermine la date de début de la fenêtre fiscale de 15 ans pour une paire.
     */
    private function getCycleStartDateForAct(?Act $lastAct, int $currentYear): DateTimeImmutable
    {
        // Si aucun acte passé, le cycle commence il y a 15 ans
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

    /**
     * Vérifie si un Don Sarkozy est utilisé dans le cycle en cours et return la valeur restante.
     */
    public function getSarkozyConsumedAmount(Person $donor, Person $beneficiary): int
    {
        // L'abattement Sarkozy est à vie, pas de limite de date.
        // On pourrait affiner en vérifiant les conditions d'âge ici ou dans le service de simulation.
        
        return $this->actRepository->getConsumedSarkozyAmount(
            $donor, $beneficiary
        );
    }
}