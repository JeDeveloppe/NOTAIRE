<?php

namespace App\Repository;

use App\Entity\NotaryOffice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotaryOffice>
 */
class NotaryOfficeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotaryOffice::class);
    }

    // src/Repository/NotaryOfficeRepository.php

    public function findNotariesCoveringLocation(float $clientLat, float $clientLng): array
    {
        return $this->createQueryBuilder('n')
            ->join('n.city', 'c') 
            // On compare les coordonnées du client (paramètres) 
            // aux coordonnées stockées dans la ville de chaque notaire (c.townHall...)
            ->where('(6371 * acos(cos(radians(:clientLat)) 
                    * cos(radians(c.townHallLatitude)) 
                    * cos(radians(c.townHallLongitude) - radians(:clientLng)) 
                    + sin(radians(:clientLat)) 
                    * sin(radians(c.townHallLatitude)))) <= n.radius')
            ->setParameter('clientLat', $clientLat)
            ->setParameter('clientLng', $clientLng)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return NotaryOffice[] Returns an array of NotaryOffice objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('n.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?NotaryOffice
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
