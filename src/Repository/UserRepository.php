<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function countActiveUsersByLocation(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.postalCode, u.city, COUNT(u.id) as count') // Ajout de u.city
            ->where('u.uniqueCode IS NOT NULL') 
            ->andWhere('u.postalCode IS NOT NULL')
            ->andWhere('u.city IS NOT NULL') // S'assurer que les deux champs sont remplis
            ->groupBy('u.postalCode, u.city') // Regroupement par CP et Ville
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les utilisateurs dans un rayon donné autour d'un point GPS
     */
    public function countUsersInRadius(float $latitude, float $longitude, int $radius, int $currentUserId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT COUNT(u.id) 
            FROM user u
            INNER JOIN city c ON u.city_id = c.id
            WHERE (6371 * acos(
                cos(radians(:lat)) * cos(radians(c.town_hall_latitude)) * cos(radians(c.town_hall_longitude) - radians(:lng)) + 
                sin(radians(:lat)) * sin(radians(c.town_hall_latitude))
            )) <= :radius
            AND u.id != :currentUserId
            AND u.is_actived = 1
            AND u.roles NOT LIKE :roleNotaire
        ';

        $result = $conn->executeQuery($sql, [
            'lat'           => $latitude,
            'lng'           => $longitude,
            'radius'        => (float) $radius,
            'currentUserId' => $currentUserId,
            'roleNotaire'   => '%ROLE_NOTAIRE%' // On exclut tous les notaires du comptage
        ]);

        return (int) $result->fetchOne();
    }
    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
