<?php
// src/EventListener/UserCodeGeneratorListener.php

namespace App\EventListener;

use App\Entity\User;
use App\Service\UniqueCodeGeneratorService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use DateTimeImmutable;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postPersist, entity: User::class)]
class UserCodeGeneratorListener
{
    public function __construct(
        private readonly UniqueCodeGeneratorService $codeGeneratorService
    ) {
    }

    /**
     * Se déclenche juste après l'enregistrement d'une nouvelle entité User en base de données.
     */
    public function postPersist(User $user, LifecycleEventArgs $event): void
    {
        // 1. Générer le code unique (le service vérifie l'unicité)
        $uniqueCode = $this->codeGeneratorService->generateUniqueCode();
        $user->setUniqueCode($uniqueCode);

        // 2. Définir la date d'expiration (6 mois après l'inscription)
        $expiresAt = (new DateTimeImmutable())->modify('+6 months');
        $user->setCodeExpiresAt($expiresAt);
        
        // 3. Persister les changements
        // postPersist est appelé APRES l'insertion. Nous devons relancer un flush 
        // pour enregistrer les mises à jour (code et date) sur l'entité déjà gérée.
        $entityManager = $event->getObjectManager();
        $entityManager->flush(); 
    }
}