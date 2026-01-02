<?php

namespace App\Repository;

use App\Entity\Notary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notary>
 */
class NotaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notary::class);
    }

    public function countNotariesInBdd()
    {
        $count = $this->createQueryBuilder('n')
            ->select('count(n.id)')
            ->where('n.isConfirmed = :status')
            ->setParameter('status', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getPerformanceStats(Notary $notary): array
    {
        // 1. On récupère les totaux par statut et la somme des points
        // On joint SimulationStatus pour accéder à sa colonne 'points'
        $results = $this->createQueryBuilder('n')
            ->select('status.code as code, COUNT(step.id) as total, SUM(status.points) as totalPoints')
            ->join('n.simulations', 'sim')
            ->join('sim.simulationSteps', 'step')
            ->join('step.status', 'status')
            ->where('n.id = :notaryId')
            ->setParameter('notaryId', $notary->getId())
            ->groupBy('status.code')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        $pointsCumules = 0;

        foreach ($results as $row) {
            $counts[$row['code']] = (int) $row['total'];
            $pointsCumules += (int) $row['totalPoints'];
        }

        // 2. Calcul du score final (Base 100 + cumul)
        $scoreGlobal = 100 + $pointsCumules;

        // Sécurité : on ne descend pas en dessous de 0
        $scoreGlobal = max(0, $scoreGlobal);

        // 3. Extraction des variables pour les taux
        $reserveds = $counts['RESERVED'] ?? 0;
        $contacts  = $counts['CONTACTED'] ?? 0;

        return [
            'score_global' => $scoreGlobal, // Le score final affiché
            'counts' => [
                'reserved' => $reserveds,
                'contacted' => $contacts,
                'closed' => $counts['CLOSED'] ?? 0,
                'expired' => $counts['EXPIRED'] ?? 0,
            ],
            'metrics' => [
                'transformation_rate' => $reserveds > 0 ? round(($contacts / $reserveds) * 100, 2) : 0,
            ],
            'is_ghost_notary' => ($reserveds > 5 && $contacts === 0),
        ];
    }

    //    /**
    //     * @return Notary[] Returns an array of Notary objects
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

    //    public function findOneBySomeField($value): ?Notary
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
