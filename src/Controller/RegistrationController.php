<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\CityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError; // ⭐️ NOUVEAU: Pour ajouter des erreurs au formulaire
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    // ⭐️ NOUVEAU: Injection du service de validation de localisation
    public function __construct(
        private readonly CityService $cityCodeLookupService
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 1. Récupération de la saisie brute de localisation (Champ non mappé)
            /** @var string|null $locationInput */
            $locationInput = $form->get('locationInput')->getData();
            /** @var string|null $selectedRole ('client' ou 'notaire') */
            $selectedRole = $form->get('userRole')->getData();
            
            // 2. 🛡️ Validation et normalisation de la localisation via le service
            $validatedLocation = $this->cityCodeLookupService->lookupCityAndCode($locationInput ?? '');

            if (!$validatedLocation) {
                // Si le service retourne NULL, la localisation n'est pas reconnue
                $form->get('locationInput')->addError(
                    new FormError('Ville ou Code Postal non reconnu par notre base de données. Veuillez vérifier votre saisie.')
                );
                // Le formulaire n'est plus valide, on retourne la vue avec l'erreur.
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }
            
            // 3. Application des données validées à l'entité User
            $user->setPostalCode($validatedLocation['postalCode']);
            $user->setCity($validatedLocation['city']);

            // 4. Encryptage du mot de passe
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // ⭐️ 5. LOGIQUE DE GESTION DU RÔLE ET DU STATUT D'ACTIVATION ⭐️
            if($selectedRole === 'notaire') {
                // Rôle Notaire en attente de vérification
                $user->setRoles(['ROLE_NOTAIRE_PENDING']);
                $user->setIsActived(false); // BLOQUÉ tant qu'il n'est pas vérifié par l'administrateur
            } else {
                // Rôle Client (par défaut)
                $user->setRoles(['ROLE_USER']);
                $user->setIsActived(true); // Actif immédiatement
            }

            // 5. Enregistrement (L'Event Listener générera le uniqueCode et codeExpiresAt ici)
            $entityManager->persist($user);
            $entityManager->flush();

            // 6. Redirection
            if ($selectedRole === 'notaire') {
                $this->addFlash(
                    'warning',
                    'Votre compte a été créé et est en attente de validation. Vous serez averti par e-mail une fois votre statut professionnel vérifié et votre compte activé.'
                );
                // Redirection vers la page de connexion ou une page d'information d'attente
                return $this->redirectToRoute('app_tree_initial_person_creation');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}