<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Entity\NotaryOffice;

class NotaryService
{
    public function __construct(
        private UserRepository $userRepo,
        private int $defaultRadius // Injecté via services.yaml
    ) {}

    public function getPotentielClients(NotaryOffice $office): int
    {
        // Détermination du rayon : 
        // Si premium -> on prend son radius personnalisé
        // Si gratuit -> on force 25 (ou la valeur du services.yaml)
        $effectiveRadius = $office->isPremium() 
            ? $office->getRadius() 
            : $this->defaultRadius;

        return $this->userRepo->countUsersInRadius(
            (float) $office->getCity()->getTownHallLatitude(),
            (float) $office->getCity()->getTownHallLongitude(),
            $effectiveRadius,
            $office->getUser()->getId()
        );
    }

    public function getActiveRadius(NotaryOffice $office): int 
    {
        // Si l'abonnement est actif et premium, on prend le rayon choisi par le notaire
        if ($office->isPremium()) {
            return $office->getRadius(); 
        }

        // Sinon, on retourne la valeur de base (25km)
        return $this->defaultRadius; // Injecté depuis services.yaml
    }
}