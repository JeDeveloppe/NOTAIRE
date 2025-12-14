<?php
// src/Repository/FiscalAbatementRuleRepository.php

namespace App\Repository;

use App\Entity\FiscalAbatementRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FiscalAbatementRule>
 */
class FiscalAbatementRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FiscalAbatementRule::class);
    }

    /**
     * Trouve la règle d'abattement classique (Donation) pour un type de lien donné.
     * * @param string $linkType Le type de lien (ex: 'parent_enfant').
     * @return FiscalAbatementRule|null
     */
    public function findClassiqueByLinkType(string $linkType): ?FiscalAbatementRule
    {
        // 1. Définir le code d'acte fiscal pour une donation classique (hors Sarkozy)
        // Ceci suppose que toutes vos règles d'abattement classique ont le TypeOfAct = 'DONATION'
        $actTypeDonation = 'DONATION'; 
        
        return $this->createQueryBuilder('r')
            // Filtre par le type de lien
            ->andWhere('r.typeOfLink = :linkType')
            ->setParameter('linkType', $linkType)
            // Filtre par le type d'acte pour n'avoir que l'abattement classique
            ->andWhere('r.typeOfAct = :actType')
            ->setParameter('actType', $actTypeDonation)
            // Limite à un seul résultat
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // Vous pouvez également ajouter ici la méthode findOneByCode pour plus de clarté dans le service:
    /**
     * @param string $code
     * @return FiscalAbatementRule|null
     */
    public function findOneByCode(string $code): ?FiscalAbatementRule
    {
        return $this->findOneBy(['code' => $code]);
    }
    
    // ... autres méthodes findBy, findOneBy, etc. générées automatiquement ...
}