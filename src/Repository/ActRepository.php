<?php
// src/Repository/ActRepository.php

namespace App\Repository;

use App\Entity\Act;
use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;

class ActRepository extends ServiceEntityRepository 
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Act::class);
    }
    
    // =======================================================
    // Méthodes requises par SimulationPlanningService
    // =======================================================

    /**
     * Calcule le montant total (en centimes) du Don Sarkozy (Art. 790 G) consommé 
     * entre une paire donnée (Donateur -> Bénéficiaire).
     */
    public function getConsumedSarkozyAmount(Person $donor, Person $beneficiary): int
    {
        // Supposons que TypeAct::CODE_SARKOZY corresponde au code fiscal 'SARKOZY'
        $sarkozyCode = \App\Service\ActService::CODE_SARKOZY;

        return (int) $this->createQueryBuilder('a')
            ->select('SUM(a.value)')
            ->leftJoin('a.typeOfAct', 't')
            ->where('a.donor = :donor')
            ->andWhere('a.beneficiary = :beneficiary')
            ->andWhere('t.code = :sarkozyCode')
            ->setParameter('donor', $donor)
            ->setParameter('beneficiary', $beneficiary)
            ->setParameter('sarkozyCode', $sarkozyCode)
            ->getQuery()
            ->getSingleScalarResult(); // Retourne le résultat directement sous forme de scalaire (int)
    }

    /**
     * Récupère tous les Actes de type Sarkozy passés pour une paire Donateur/Bénéficiaire.
     */
    public function findSarkozyActs(Person $donor, Person $beneficiary): array
    {
        $sarkozyCode = \App\Service\ActService::CODE_SARKOZY;

        return $this->createQueryBuilder('a')
            ->leftJoin('a.typeOfAct', 't')
            ->where('a.donor = :donor')
            ->andWhere('a.beneficiary = :beneficiary')
            ->andWhere('t.code = :sarkozyCode')
            ->setParameter('donor', $donor)
            ->setParameter('beneficiary', $beneficiary)
            ->setParameter('sarkozyCode', $sarkozyCode)
            ->orderBy('a.dateOfAct', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    // (Incluses pour la complétude, si vous ne l'aviez pas fait pour l'erreur findNonPrescribedActs)

    /**
     * Trouve tous les Actes qui ne sont pas encore prescrits par la date donnée.
     */
    public function findNonPrescribedActs(Person $donor, Person $beneficiary, DateTimeImmutable $prescriptionDate): array
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->where('a.donor = :donor')
            ->andWhere('a.beneficiary = :beneficiary')
            ->andWhere('a.dateOfAct > :prescriptionDate') 
            ->setParameter('donor', $donor)
            ->setParameter('beneficiary', $beneficiary)
            ->setParameter('prescriptionDate', $prescriptionDate)
            ->getQuery()
            ->getResult();
    }
    
    // ... (Ajouter ici les autres méthodes qui pourraient manquer : findLatestActForPair, getConsumedAbatementForCycle)
    
    // =======================================================
    // Autres méthodes de ActRepository (à vérifier)
    // =======================================================
    
    /**
     * Trouve l'acte de donation le plus récent entre un donateur et un bénéficiaire.
     */
    public function findLatestActForPair(int $donorId, int $beneficiaryId): ?Act
    {
        return $this->createQueryBuilder('a')
            ->where('a.donor = :donorId')
            ->andWhere('a.beneficiary = :beneficiaryId')
            ->setParameter('donorId', $donorId)
            ->setParameter('beneficiaryId', $beneficiaryId)
            ->orderBy('a.dateOfAct', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    /**
     * Calcule l'abattement classique consommé depuis la date de début de cycle.
     */
    public function getConsumedAbatementForCycle(Person $donor, Person $beneficiary, DateTimeImmutable $cycleStartDate): int
    {
        // Supposons que nous excluons les actes SARKOZY dans cette somme
        $sarkozyCode = \App\Service\ActService::CODE_SARKOZY;

        $consumed = $this->createQueryBuilder('a')
            ->select('SUM(a.value)')
            ->leftJoin('a.typeOfAct', 't')
            ->where('a.donor = :donor')
            ->andWhere('a.beneficiary = :beneficiary')
            ->andWhere('a.dateOfAct >= :cycleStartDate') // Seulement les actes DANS la fenêtre des 15 ans
            ->andWhere('t.code != :sarkozyCode') // Exclure Sarkozy car il a sa propre enveloppe
            ->setParameter('donor', $donor)
            ->setParameter('beneficiary', $beneficiary)
            ->setParameter('cycleStartDate', $cycleStartDate)
            ->setParameter('sarkozyCode', $sarkozyCode)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $consumed;
    }
}