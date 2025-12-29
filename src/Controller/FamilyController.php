<?php

namespace App\Controller;

use App\Entity\Person;
use App\Form\PersonType;
use App\Repository\DonationRepository;
use App\Service\DonationService;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/famille')]
#[IsGranted('ROLE_USER')]
final class FamilyController extends AbstractController
{
    #[Route('/', name: 'app_family_dashboard')]
    public function dashboard(PersonRepository $personRepository, DonationService $donationService, DonationRepository $donationRepository): Response
    {
        $user = $this->getUser();
        $people = $user->getPeople();

        // On récupère les stats de base (dons actifs/expire)
        $stats = $donationService->getUserDashboardStats($user);
        $donations = $donationRepository->findAllDonationsByUser($user);

        // CALCUL DU TOTAL SANS TOUCHER AU SERVICE
        $totalAvailable = 0;
        foreach ($people as $person) {
            $bilan = $donationService->getFullPatrimonialBilan($person);
            $totalAvailable += $bilan['totalGlobal'];
        }

        return $this->render('family/dashboard.html.twig', array_merge($stats, [
            'people' => $people,
            'totalAvailableAllowance' => $totalAvailable, // On injecte la variable attendue par le template
            'donations' => $donations
        ]));
    }

    #[Route('/personne/ajouter-un-membre', name: 'app_person_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, PersonRepository $repo): Response
    {
        $user = $this->getUser();
        $person = new Person();

        $isFirst = $repo->count(['owner' => $user]) === 0;

        $form = $this->createForm(PersonType::class, $person, [
            'is_first_person' => $isFirst,
            'user' => $user
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $person->setOwner($user);
            $em->persist($person);
            $em->flush();

            $this->addFlash('success', 'Le membre a été ajouté.');
            return $this->redirectToRoute('app_family_dashboard');
        }

        return $this->render('family/person/new.html.twig', [
            'form' => $form->createView(),
            'is_first' => $isFirst
        ]);
    }

    #[Route('/personne/modifier/{id}', name: 'app_person_edit', methods: ['GET', 'POST'])]
    public function edit(Person $person, Request $request, EntityManagerInterface $em): Response
    {
        // SÉCURITÉ : On vérifie que la personne appartient bien à l'utilisateur
        if ($person->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de modifier cette personne.");
        }

        $form = $this->createForm(PersonType::class, $person, [
            'is_first_person' => false,
            'user' => $this->getUser()
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('info', 'Les informations ont été mises à jour.');
            return $this->redirectToRoute('app_family_dashboard');
        }

        return $this->render('family/person/edit.html.twig', [
            'form' => $form,
            'person' => $person,
            'is_first' => false
        ]);
    }

    #[Route('/personne/supprimer/{id}', name: 'app_person_delete', methods: ['POST'])]
    public function delete(Request $request, Person $person, EntityManagerInterface $em): Response
    {
        if ($person->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Sécurité contre les attaques CSRF (bouton de suppression)
        if ($this->isCsrfTokenValid('delete' . $person->getId(), $request->request->get('_token'))) {
            $em->remove($person);
            $em->flush();
            $this->addFlash('danger', 'Le membre a été retiré de la famille.');
        }

        return $this->redirectToRoute('app_family_dashboard');
    }

    #[Route('/personne/{id}/arbre', name: 'app_person_tree', methods: ['GET'])]
    public function treeFromPerson(Person $person, DonationService $donationService): Response
    {
        if ($person->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // On récupère le tableau complet du service
        $bilan = $donationService->getFullPatrimonialBilan($person);

        return $this->render('family/person/tree.html.twig', $bilan);
    }
}
