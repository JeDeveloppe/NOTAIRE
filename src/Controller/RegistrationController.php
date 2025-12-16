<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    // L'injection du CityService n'est plus nécessaire ici.

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        // Assurez-vous d'importer la classe CityAutocompleteField dans ce fichier si elle n'est pas déjà dans le namespace global
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 1. Récupération des données non mappées
            /** @var string|null $selectedRole ('client' ou 'notaire') */
            $selectedRole = $form->get('userRole')->getData();
            
            // 2. Encryptage du mot de passe
            // La ville est déjà mappée à $user grâce à $form->handleRequest($request) et à l'autocomplétion.
            
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // ⭐️ 3. LOGIQUE DE GESTION DU RÔLE ET DU STATUT D'ACTIVATION ⭐️
            if($selectedRole === 'notaire') {
                // Rôle Notaire en attente de vérification
                $user->setRoles(['ROLE_NOTAIRE_PENDING']);
                $user->setIsActived(false); // BLOQUÉ tant qu'il n'est pas vérifié par l'administrateur
            } else {
                // Rôle Client (par défaut)
                $user->setRoles(['ROLE_USER']);
                $user->setIsActived(true); // Actif immédiatement
            }

            // 4. Enregistrement
            $entityManager->persist($user);
            $entityManager->flush();

            // 5. Redirection
            if ($selectedRole === 'notaire') {
                $this->addFlash(
                    'warning',
                    'Votre compte a été créé et est en attente de validation. Vous serez averti par e-mail une fois votre statut professionnel vérifié et votre compte activé.'
                );
                // Redirection vers la page de connexion ou une page d'information d'attente
                return $this->redirectToRoute('app_login'); // Redirection modifiée pour plus de clarté
            }
            
            // Redirection standard pour les clients (ou si l'activation par e-mail est requise)
            return $this->redirectToRoute('app_home'); // Remplacer par la route souhaitée après inscription client
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}