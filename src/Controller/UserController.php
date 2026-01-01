<?php
// src/Controller/AccountController.php

namespace App\Controller;

use App\Form\User\UserCityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserCityType::class, $user, [
            'action' => $this->generateUrl('app_account_update_city'),
        ]);

        return $this->render('user/account.html.twig', [
            'user' => $user,
            'cityForm' => $form->createView(),
        ]);
    }

    #[Route('/mon-compte/update-city', name: 'app_account_update_city', methods: ['POST'])]
    public function updateCity(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserCityType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Votre ville a été mise à jour.');
        }

        return $this->redirectToRoute('app_account');
    }

    #[Route('/mon-compte/supprimer', name: 'app_account_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $user = $this->getUser();

        // Vérification du jeton CSRF pour la sécurité
        if ($this->isCsrfTokenValid('delete-account', $request->request->get('_token'))) {

            // Si c'est un notaire, tu pourrais ajouter une logique ici 
            // pour résilier l'abonnement Stripe avant la suppression.

            $entityManager->remove($user);
            $entityManager->flush();

            // On invalide la session pour déconnecter l'utilisateur
            $session->invalidate();
            $this->container->get('security.token_storage')->setToken(null);

            $this->addFlash('success', 'Votre compte et vos données ont été définitivement supprimés.');

            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('danger', 'Une erreur est survenue lors de la tentative de suppression.');
        return $this->redirectToRoute('app_account');
    }
}
