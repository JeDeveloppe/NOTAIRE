<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use DateTimeImmutable;

class TemporaryAccessService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * Valide le code unique et l'expiration, et récupère l'utilisateur associé.
     * * @param string $uniqueCode Le code fourni par le notaire (ex: 'AG2Y-H7D4').
     * @return User L'utilisateur valide dont les données (Personnes et Actes) doivent être utilisées.
     * @throws AccessDeniedException Si le code est invalide, non trouvé, ou expiré.
     */
    public function getUserByTemporaryCode(string $uniqueCode): User
    {
        // 1. Recherche de l'utilisateur par le code unique
        /** @var User|null $user */
        $user = $this->userRepository->findOneBy(['uniqueCode' => $uniqueCode]);

        if (!$user) {
            // Refuser l'accès sans indiquer si le code n'existe pas ou est déjà nettoyé
            throw new AccessDeniedException("Code d'accès invalide.");
        }
        
        // 2. Vérification de l'expiration
        // On utilise la méthode de vérification de l'entité User (isCodeExpired())
        // si vous l'avez implémentée, sinon on vérifie la date ici :
        
        if ($user->getCodeExpiresAt() !== null && $user->getCodeExpiresAt() < new DateTimeImmutable()) {
            
            // Le code est dans la base mais est expiré. 
            // Bien qu'il sera nettoyé par la commande Cron, nous le refusons ici.
            throw new AccessDeniedException("Le code d'accès a expiré. Veuillez contacter votre client pour un renouvellement.");
        }
        
        // Si le code est non-NULL et non expiré, il est valide.
        return $user;
    }
}