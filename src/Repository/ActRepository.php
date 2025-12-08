<?php

namespace App\Repository;

use App\Entity\Act;
use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;

/**
 * @extends ServiceEntityRepository<Act>
 */
class ActRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Act::class);
    }

    /**
     * 1. Trouve le dernier acte entre le donateur et le bénéficiaire.
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
     * 2. Récupère la somme totale des abattements consommés depuis une date de début de cycle.
     */
    public function getConsumedAbatementForCycle(Person $donor, Person $beneficiary, DateTimeImmutable $cycleStartDate): int
    {
        return $this->createQueryBuilder('a')
            ->select('SUM(a.consumedAbatement)')
            ->where('a.donor = :donor')
            ->andWhere('a.beneficiary = :beneficiary')
            ->andWhere('a.dateOfAct >= :startDate')
            ->setParameter('donor', $donor)
            ->setParameter('beneficiary', $beneficiary)
            ->setParameter('startDate', $cycleStartDate)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * 3. ✅ MÉTHODE MANQUANTE : Vérifie l'existence d'un acte de type "Don Sarkozy" dans le cycle en cours.
     */
    public function findSarkozyActForCycle(Person $donor, Person $beneficiary, DateTimeImmutable $cycleStartDate): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.donor = :donor')
            ->andWhere('a.beneficiary = :beneficiary')
            ->andWhere('a.dateOfAct >= :startDate')
            // NOTE : Si vous avez un champ 'type' sur votre entité Act pour filtrer le type Sarkozy, ajoutez-le ici.
            ->setParameter('donor', $donor)
            ->setParameter('beneficiary', $beneficiary)
            ->setParameter('startDate', $cycleStartDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}