<?php

namespace App\Repository;

use App\Entity\Notary;
use App\Entity\Country;
use App\Entity\Simulation;
use App\Entity\SimulationStatus;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

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

    public function countByCities(array $cityIds): int
    {
        if (empty($cityIds)) return 0;

        return $this->createQueryBuilder('s')
            ->select('count(s.id)')
            ->innerJoin('s.user', 'u')
            ->where('u.city IN (:cityIds)')
            ->setParameter('cityIds', $cityIds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Return le nombre de simulation ouvert par pays du notaire
     *
     * @param integer $limit: nombre de resultats a renvoyer
     * @param Notary $notary
     * @return array
     */
    public function findLastInCountry(int $limit = 10, Notary $notary): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.user', 'u') // On joint l'utilisateur (le client) qui a créé la simulation
            ->join('u.city', 'c')
            ->where('s.status = :status')
            ->andWhere('c.country = :country') // On filtre : le pays du client doit être celui du notaire
            ->andWhere('s.reservedBy IS NULL')
            ->setParameter('status', 'OPEN') //! doit etre comme service.yaml
            ->setParameter('country', $notary->getCity()->getCountry())
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getQueryByZipCodes(array $zipCodes, Notary $notary): \Doctrine\ORM\Query
    {
        $status = $this->getEntityManager()
            ->getRepository(SimulationStatus::class)
            ->findOneBy(['code' => 'OPEN']);

        $qb = $this->createQueryBuilder('s')
            ->addSelect('u', 'c') // Optimisation : évite 1 requête par user/city
            ->innerJoin('s.user', 'u')
            ->innerJoin('u.city', 'c')
            ->where('s.status = :status')
            ->andWhere('c.postalCode IN (:zips)')
            ->andWhere('s.reservedBy IS NULL')
            ->andWhere('c.country = :country')
            ->setParameter('zips', $zipCodes)
            ->setParameter('status', $status)
            ->setParameter('country', $notary->getCity()->getCountry())
            ->orderBy('s.createdAt', 'DESC');

        return $qb->getQuery();
    }

    // 2. Ta méthode existante qui utilise désormais la méthode "Mère"
    public function findByZipCodes(array $zipCodes, Notary $notary, int $limit = null): array
    {
        $query = $this->getQueryByZipCodes($zipCodes, $notary);

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->getResult();
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
