<?php

namespace App\Controller;

use App\Entity\Donation;
use App\Form\DonationType;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/famille/donation')]
#[IsGranted('ROLE_USER')]
class DonationController extends AbstractController
{
    /**
     * Liste complète des dons pour la gestion (Suppression/Édition)
     */
    #[Route('/', name: 'app_donation_index', methods: ['GET'])]
    public function index(DonationRepository $donationRepository): Response
    {
        $user = $this->getUser();

        // On récupère tous les dons liés aux membres de l'utilisateur
        // On peut utiliser une requête personnalisée ou filtrer via les relations
        $donations = $donationRepository->createQueryBuilder('d')
            ->join('d.donor', 'p')
            ->where('p.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('donation/liste.html.twig', [
            'donations' => $donations,
        ]);
    }

    #[Route('/nouveau', name: 'app_donation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $donation = new Donation();

        $form = $this->createForm(DonationType::class, $donation, [
            'user' => $user,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $donation->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
            $entityManager->persist($donation);
            $entityManager->flush();

            $this->addFlash('success', 'Le don a bien été enregistré.');

            // Turbo gère très bien les redirections standards vers le Dashboard
            return $this->redirectToRoute('app_donation_index');
        }

        // --- CORRECTION TURBO ICI ---
        // Si le formulaire est soumis mais invalide, on renvoie un code 422
        $response = new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);

        return $this->render('donation/new.html.twig', [
            'donation' => $donation,
            'form' => $form->createView(),
        ], $response);
    }

    #[Route('/supprimer/{id}', name: 'app_donation_delete', methods: ['POST'])]
    public function delete(Request $request, Donation $donation, EntityManagerInterface $entityManager): Response
    {
        if ($donation->getDonor()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $donation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($donation);
            $entityManager->flush();
            $this->addFlash('info', 'Le don a été supprimé.');
        }

        // On redirige vers la liste de gestion plutôt que le dashboard
        return $this->redirectToRoute('app_donation_index');
    }

    #[Route('/modifier/{id}', name: 'app_donation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Donation $donation, EntityManagerInterface $entityManager): Response
    {
        // Sécurité
        if ($donation->getDonor()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(DonationType::class, $donation, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le don a été mis à jour.');
            return $this->redirectToRoute('app_donation_index');
        }

        return $this->render('donation/edit.html.twig', [
            'donation' => $donation,
            'form' => $form->createView(),
        ]);
    }
}
