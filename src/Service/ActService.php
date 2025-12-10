<?php

namespace App\Service;

use App\Entity\Act;
use App\Entity\Person;
use App\Entity\TypeAct;
use App\Repository\ActRepository;
use Symfony\Bundle\SecurityBundle\Security; 
use DateTimeImmutable;

class ActService
{
    // Constantes Fiscales 
    public const CODE_SARKOZY = 'SARKOZY'; 
    public const CODE_CLASSIQUE = 'CLASSIQUE'; 
    public const CODE_USUFRUIT = 'USUFRUIT'; 
    public const ABATEMENT_CYCLE_YEARS = 15;

    private const SECONDS_IN_15_YEARS = 15 * 365.25 * 24 * 60 * 60; 

    public function __construct(
        private readonly ActRepository $actRepository,
        private readonly Security $security
    ) {
    }

    // =======================================================
    // LOGIQUE FISCALE DES ACTES PASSÉS
    // =======================================================

    /**
     * Calcule le montant de l'abattement classique consommé (en centimes) par un acte donné.
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
     * @param int $donorId ID de la personne Donateur
     * @param int $beneficiaryId ID de la personne Bénéficiaire
     * @return Act|null L'acte le plus récent, ou null s'il n'y en a pas.
     */
    public function findLatestActForPair(int $donorId, int $beneficiaryId): ?Act
    {
        // ⬅️ Utilise le nom de méthode correct de votre Repository
        return $this->actRepository->findLatestActForPair($donorId, $beneficiaryId);
    }

    // =======================================================
    // LOGIQUE DE CYCLE FISCAL 
    // =======================================================
    
    /**
     * Détermine le statut du cycle d'abattement (point de départ, abattement consommé)
     * pour une paire Donateur/Bénéficiaire donnée.
     * * @param int $maxAbatement La valeur max de l'abattement pour cette relation.
     */
    public function getCycleStatus(Person $donor, Person $beneficiary, int $maxAbatement, ?Act $lastAct, int $currentYear): array
    {
        $cycleStartDate = $this->getCycleStartDateForAct($lastAct, $currentYear);
        $nextFullResetYear = $currentYear + self::ABATEMENT_CYCLE_YEARS;
        
        // 1. Calcul de l'année du prochain reset complet (15 ans depuis le dernier Act)
        if ($lastAct) {
            $lastActYear = (int) $lastAct->getDateOfAct()->format('Y');
            $yearsSinceLastAct = $currentYear - $lastActYear;
            $cyclesPassed = floor($yearsSinceLastAct / self::ABATEMENT_CYCLE_YEARS);
            $nextFullResetYear = $lastActYear + (($cyclesPassed + 1) * self::ABATEMENT_CYCLE_YEARS); 
        }

        // 2. Calcul de l'abattement consommé
        $consumedAbatement = $this->actRepository->getConsumedAbatementForCycle(
            $donor, $beneficiary, $cycleStartDate
        );
        
        // 3. Calcul de l'abattement disponible
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
        // Pas besoin de limite de date, car c'est une enveloppe à vie.
        return $this->actRepository->getConsumedSarkozyAmount(
            $donor, $beneficiary
        );
    }
}