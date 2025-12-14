<?php
// src/Security/UserStatusChecker.php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface; // NOUVEL IMPORT NÉCESSAIRE
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class UserStatusChecker implements UserCheckerInterface
{
    /**
     * Vérification effectuée AVANT que l'utilisateur ne soit authentifié.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActived()) { 
            throw new CustomUserMessageAuthenticationException(
                'Votre compte n\'est pas encore actif. Si vous êtes notaire, veuillez attendre la vérification par nos services.'
            );
        }
    }

    /**
     * Vérification effectuée APRÈS que l'utilisateur a été authentifié.
     * La signature DOIT inclure le paramètre TokenInterface.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void // ⭐️ CORRECTION ICI ⭐️
    {
        // Laisser vide ou ajouter la logique post-authentification si nécessaire
    }
}