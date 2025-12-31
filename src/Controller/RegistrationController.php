<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Notary;
use App\Form\RegistrationFormType;
use App\Form\RegistrationNotaryFormType;
use Doctrine\ORM\EntityManagerInterface;
use App\Security\UserCustomAuthenticator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // do anything else you need here, like send an email

            return $security->login($user, UserCustomAuthenticator::class, 'main');
        }

        return $this->render('registration/user.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/inscription/notaire', name: 'app_register_notary', methods: ['GET', 'POST'])]
    public function subscribeChoice(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $userPasswordHasher,
    ): Response {
        $notary = new Notary();
        $form = $this->createForm(RegistrationNotaryFormType::class, $notary);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Création de l'utilisateur (lié au Notaire)
            $user = new User();
            $user->setEmail($form->get('email')->getData());
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            $user->setRoles(['ROLE_NOTARY']);

            // 2. Lier l'utilisateur au notaire (si votre entité Notary a un champ user)
            $notary->setUser($user);

            $entityManager->persist($user);
            $entityManager->persist($notary);
            $entityManager->flush();

            // Redirection vers le paiement (Stripe) ou confirmation
            return $this->redirectToRoute('app_home'); 
        }

        // Récupération des données pour votre template Twig
        return $this->render('registration/notary.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
