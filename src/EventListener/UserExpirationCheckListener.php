<?php
// src/EventListener/UserExpirationCheckListener.php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use DateTimeImmutable;

class UserExpirationCheckListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Vérifie si le code unique de l'utilisateur est expiré à la connexion.
     */
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        // 1. S'assurer que l'objet est bien notre entité User
        if (!$user instanceof User) {
            return;
        }

        // 2. Vérification de la date d'expiration
        // On vérifie seulement si l'utilisateur a un code et une date d'expiration
        if ($user->getUniqueCode() !== null && $user->getCodeExpiresAt() !== null) {
            
            // Si la date d'expiration est strictement inférieure à l'instant présent
            if ($user->getCodeExpiresAt() < new DateTimeImmutable()) {
                
                // 3. Le code est expiré : on le désactive immédiatement
                $user->setUniqueCode(null);
                $user->setCodeExpiresAt(null);
                
                // 4. Persister le changement
                // Cela met à jour la base de données sans attendre la commande Cron
                $this->entityManager->flush();
            }
        }
    }
}