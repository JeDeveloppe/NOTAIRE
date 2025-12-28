<?php

namespace App\Controller;

use App\Entity\Donation;
use App\Form\DonationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/donation')]
#[IsGranted('ROLE_USER')]
class DonationController extends AbstractController
{
    #[Route('/nouveau', name: 'app_donation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $donation = new Donation();
        
        // On passe l'utilisateur au formulaire pour filtrer les listes déroulantes
        $form = $this->createForm(DonationType::class, $donation, [
            'user' => $user,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($donation);
            $entityManager->flush();

            $this->addFlash('success', 'Le don a bien été enregistré.');

            return $this->redirectToRoute('app_family_dashboard');
        }

        return $this->render('donation/new.html.twig', [
            'donation' => $donation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/supprimer/{id}', name: 'app_donation_delete', methods: ['POST'])]
    public function delete(Request $request, Donation $donation, EntityManagerInterface $entityManager): Response
    {
        // Sécurité : on vérifie que le donateur du don appartient bien à l'utilisateur
        if ($donation->getDonor()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$donation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($donation);
            $entityManager->flush();
            $this->addFlash('info', 'Le don a été supprimé.');
        }

        return $this->redirectToRoute('app_family_dashboard');
    }
}