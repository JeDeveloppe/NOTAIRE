<?php

namespace App\Controller;

use App\Service\OfferService;
use App\Repository\OfferRepository;
use App\Repository\SimulationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class NotaryController extends AbstractController
{
    public function __construct(
        private SimulationRepository $simulationRepository
    ) {}

    /**
     * Méthode utilitaire interne pour vérifier le statut du notaire.
     * Elle gère les redirections si le profil est incomplet ou non validé.
     */
    private function checkNotaryStatus(): ?Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $notary = $user->getNotary();

        // 1. Si l'entité Notary n'existe pas encore (profil à remplir)
        if (!$notary) {
            return $this->redirectToRoute('app_notary_my_study');
        }

        // 2. Si le notaire existe mais n'est pas encore confirmé par l'admin
        if (!$notary->isConfirmed()) {
            return $this->redirectToRoute('app_site_notary_pending_verification');
        }

        return null; // Tout est en ordre
    }

    #[Route('/notaire/presentation', name: 'app_site_notary_presentation')]
    public function presentation(OfferRepository $offerRepository): Response
    {
        $mainOffers = $offerRepository->findBy(['isAddon' => false, 'isOnWebSite' => true]);
        $addons = $offerRepository->findBy(['isAddon' => true, 'isOnWebSite' => true]);

        return $this->render('site/notary/presentation.html.twig', [
            'mainOffers' => $mainOffers,
            'addons' => $addons,
            'active_offices' => 120,
            'leads_generated' => 1500,
        ]);
    }

    #[Route('/notaire/verification', name: 'app_site_notary_pending_verification')]
    public function pendingVerification(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();

        // Si le notaire est déjà validé, on ne le laisse pas sur cette page
        if ($notary && $notary->isConfirmed()) {
            return $this->redirectToRoute('app_notary_dashboard');
        }

        return $this->render('notary/pending_verification.html.twig');
    }

    #[Route('/notaire', name: 'app_notary_dashboard')]
    public function dashboard(OfferService $offerService): Response
    {
        if ($response = $this->checkNotaryStatus()) {
            return $response;
        }

        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();
        $activeSub = $notary->getActiveSubscription();
        $hasActiveOffer = ($activeSub !== null);

        // 1. Déterminer quels dossiers on affiche en "Teasing" ou "Zone"
        if ($hasActiveOffer) {
            $zipCodes = array_map(fn($z) => $z->getPostalCode(), $notary->getSelectedZipCodes()->toArray());
            $availableSimulations = $this->simulationRepository->findByZipCodes($zipCodes);
        } else {
            // Teasing National (Derniers dossiers du pays du notaire)
            $availableSimulations = $this->simulationRepository->findLastInCountry(10, $notary);
        }

        // 2. Calcul des stats
        $totalSlots = $offerService->getTotalAllowedSectors($notary);
        $usedSlots = count($notary->getSelectedZipCodes());

        return $this->render('notary/dashboard.html.twig', [
            'notary' => $notary,
            'hasActiveOffer' => $hasActiveOffer,
            'activeSubscription' => $activeSub,
            'availableSimulations' => $availableSimulations, // Dossiers à saisir
            'stats' => [
                'totalSlots' => $totalSlots,
                'usedSlots' => $usedSlots,
                'percentage' => ($totalSlots > 0) ? ($usedSlots / $totalSlots) * 100 : 0,
                'leadsInZone' => count($availableSimulations),
                'selectedLeads' => count($notary->getSimulations()), // Dossiers déjà pris
            ]
        ]);
    }

    #[Route('/notaire/mon-etude', name: 'app_notary_my_study')]
    public function myStudy(): Response
    {
        // Si le notaire a déjà un profil, on le redirige vers le dashboard
        // qui s'occupera de vérifier s'il est confirmé ou non.
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getNotary()) {
            return $this->redirectToRoute('app_notary_dashboard');
        }

        return $this->render('notary/complete_profile.html.twig');
    }

    #[Route('/notaire/offres', name: 'app_notary_pricing')]
    public function pricing(OfferRepository $offerRepository): Response
    {
        if ($response = $this->checkNotaryStatus()) {
            return $response;
        }

        return $this->render('notary/pricing.html.twig', [
            'offers' => $offerRepository->findBy([
                'isAddon' => false,
                'isOnWebSite' => true
            ], ['baseNotariesCount' => 'ASC']),
        ]);
    }

    #[Route('/notaire/souscrire', name: 'app_notary_subscribe')]
    public function subscribe(Request $request, OfferRepository $offerRepository): Response
    {
        if ($response = $this->checkNotaryStatus()) {
            return $response;
        }

        $preselectedId = $request->query->get('preselect');
        $mainOffers = $offerRepository->findBy(['isAddon' => false, 'isOnWebSite' => true]);
        $addons = $offerRepository->findBy(['isAddon' => true, 'isOnWebSite' => true]);

        return $this->render('notary/subscribe_choice.html.twig', [
            'mainOffers' => $mainOffers,
            'addons' => $addons,
            'preselectedId' => $preselectedId
        ]);
    }
}
