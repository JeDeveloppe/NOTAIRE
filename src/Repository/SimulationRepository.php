<?php

namespace App\Repository;

use App\Entity\Simulation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Simulation>
 */
class SimulationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Simulation::class);
    }

    public function countTotalForPublic(): int
    {
        return $this->createQueryBuilder('s')
            ->select('count(s.id)')
            // Optionnel : ne compter que si le dossier a avancé (preuve de succès)
            // ->where('s.status != :status')
            // ->setParameter('status', 'OPEN')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Simulation[] Returns an array of Simulation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Simulation
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
