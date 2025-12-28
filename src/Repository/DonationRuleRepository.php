<?php

namespace App\Repository;

use App\Entity\DonationRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DonationRule>
 */
class DonationRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DonationRule::class);
    }

    public function findByRelationshipCode(string $code): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.relationship', 'r')
            ->where('r.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getResult(); // Retourne un tableau d'objets DonationRule
    }
    //    /**
    //     * @return DonationRule[] Returns an array of DonationRule objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?DonationRule
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
