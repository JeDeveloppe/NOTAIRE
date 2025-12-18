<?php

namespace App\Controller;

use App\Service\SubscriptionService;
use App\Repository\SubscriptionTypeRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/notaire/subscription', name: 'notaire_subscription_')]
#[IsGranted('ROLE_NOTAIRE')] // Sécurité pour s'assurer que seul un notaire y accède
class NotarySubscriptionController extends AbstractController
{
    #[Route('/activate', name: 'activate', methods: ['POST', 'GET'])]
    public function activate(SubscriptionService $subscriptionService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $office = $user->getNotaryOffice();

        if (!$office) {
            $this->addFlash('danger', "Vous devez d'abord configurer votre étude.");
            return $this->redirectToRoute('notaire_edit_office');
        }

        try {
            dd('il faut faire le paiement');
            // Ici, normalement, on vérifierait le paiement avant d'activer
            $subscriptionService->activatePremium($office);
            
            $this->addFlash('success', 'Félicitations ! Votre étude est désormais X pour X mois.');
        } catch (\Exception $e) {
            $this->addFlash('danger', "Erreur lors de l'activation : " . $e->getMessage());
        }

        return $this->redirectToRoute('notaire_edit_office');
    }

    #[Route('/notaire/subscription/plan', name: 'plan', methods: ['GET'])]
    #[IsGranted('ROLE_NOTAIRE')] // Sécurité pour s'assurer que seul un notaire y accède
    public function plan(SubscriptionTypeRepository $typeRepo): Response
    {
        $premiumPlan = $typeRepo->findOneBy(['name' => 'Premium']);
        $defaultRadius = $this->getParameter('app.default_notary_radius');

        return $this->render('notaire/subscription/plan.html.twig', [
            'plan' => $premiumPlan,
            'defaultRadius' => $defaultRadius
        ]);
    }
}