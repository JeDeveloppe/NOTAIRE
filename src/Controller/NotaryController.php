<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\NotaryService;
use App\Service\OfferService;
use App\Service\OptimizationService;
use App\Repository\OfferRepository;
use App\Repository\CityRepository;
use App\Repository\SimulationRepository;
use App\Form\Notary\ZoneCoverageType;
use App\Repository\NotaryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotaryController extends AbstractController
{
    public function __construct(
        private NotaryService $notaryService,
        private SimulationRepository $simulationRepository,
        private OfferRepository $offerRepository,
        private CityRepository $cityRepository,
        private OfferService $offerService,
        private OptimizationService $optimizationService,
        private NotaryRepository $notaryRepository
    ) {}

    /**
     * Vérification de sécurité et de statut du profil Notaire
     */
    private function checkNotaryStatus(): ?Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        $notary = $user->getNotary();
        if (!$notary) return $this->redirectToRoute('app_notary_my_study');
        if (!$notary->isConfirmed()) return $this->redirectToRoute('app_site_notary_pending_verification');

        return null;
    }

    #[Route('/notaire/presentation', name: 'app_site_notary_presentation')]
    public function presentation(): Response
    {
        return $this->render('site/notary/presentation.html.twig', [
            'mainOffers' => $this->offerRepository->findBy(['isAddon' => false, 'isOnWebSite' => true]),
            'addons' => $this->offerRepository->findBy(['isAddon' => true, 'isOnWebSite' => true]),
            'active_offices' => 120,
            'leads_generated' => 1500,
        ]);
    }

    #[Route('/notaire', name: 'app_notary_dashboard')]
    public function dashboard(): Response
    {
        if ($response = $this->checkNotaryStatus()) return $response;

        $notary = $this->getUser()->getNotary();
        $activeSub = $this->notaryService->getActiveSubscription($notary);
        
        $availableSimulations = $this->notaryService->getAvailableSimulations($notary, 11);
        
        $groupedSectors = [];
        foreach ($notary->getSelectedZipCodes() as $sz) {
            $cp = $sz->getCity()->getPostalCode();
            if (!isset($groupedSectors[$cp])) {
                $groupedSectors[$cp] = $this->cityRepository->findBy(['postalCode' => $cp], ['name' => 'ASC']);
            }
        }

        //les stats du notaire
        $notaryStats = $this->notaryRepository->getPerformanceStats($notary);

        return $this->render('notary/dashboard.html.twig', [
            'notary' => $notary,
            'hasActiveOffer' => ($activeSub !== null),
            'activeSubscription' => $activeSub,
            'availableSimulations' => array_slice($availableSimulations, 0, 10),
            'groupedSectors' => $groupedSectors,
            'stats' => [
                'totalSlots' => $this->offerService->getTotalAllowedSectors($notary, $activeSub),
                'usedSlots' => count($notary->getSelectedZipCodes()),
                'leadsInZone' => count($availableSimulations),
                'selectedLeads' => $notary->getSimulations()->count(),
            ],
            'viewAllSimulations' => count($availableSimulations) > 10,
            'notaryStats' => $notaryStats
        ]);
    }

    #[Route('/notaire/simulation/{reference}', name: 'app_notary_simulation_view')]
    public function viewSimulationByNotary(string $reference): Response
    {
        if ($response = $this->checkNotaryStatus()) return $response;

        $notary = $this->getUser()->getNotary();
        $simulation = $this->simulationRepository->findOneBy(['reference' => $reference]);

        if (!$simulation) throw $this->createNotFoundException("Dossier introuvable.");

        $clientZip = $simulation->getUser()->getCity()->getPostalCode();
        $notaryZips = $this->notaryService->getNotaryZipCodes($notary);
        $isOwner = ($simulation->getReservedBy() === $notary);

        if (!$isOwner && !in_array($clientZip, $notaryZips)) {
            throw $this->createAccessDeniedException("Zone non couverte.");
        }

        return $this->render('notary/simulation_view.html.twig', [
            'simulation' => $simulation,
            'client' => $simulation->getUser(),
            'analysis' => $this->optimizationService->getDonationAnalyses($simulation->getUser()),
            'familyPlan' => $this->optimizationService->getGlobalFamilyPlan($simulation->getUser()),
            'isOwner' => $isOwner,
            'notary' => $notary
        ]);
    }

    #[Route('/notaire/simulation/reserve/{code}', name: 'app_notary_reserve_simulation')]
    public function reserve(string $code, Request $request): Response
    {
        $notary = $this->getUser()->getNotary();
        $simulation = $this->simulationRepository->findOneBy(['reference' => $code]);

        if (!$simulation) throw $this->createNotFoundException();

        if ($this->notaryService->reserveSimulation($simulation, $notary)) {
            $this->addFlash('success', "Dossier #$code ajouté avec succès à votre étude.");
        } else {
            $this->addFlash('danger', "Ce dossier a déjà été sélectionné par un autre office.");
        }

        $target = ($request->query->get('from') === 'dashboard') ? 'app_notary_dashboard' : 'app_site_notary_all_simulations';
        return $this->redirectToRoute($target);
    }

    #[Route('/notaire/simulations/{page}', name: 'app_site_notary_all_simulations', defaults: ['page' => 1])]
    public function simulations(int $page, PaginatorInterface $paginator): Response
    {
        $notary = $this->getUser()->getNotary();
        $zipCodes = $this->notaryService->getNotaryZipCodes($notary);

        $query = $this->simulationRepository->getQueryByZipCodes($zipCodes, $notary);
        $pagination = $paginator->paginate($query, $page, 20);

        foreach ($pagination->getItems() as $sim) {
            $this->notaryService->injectOptimizationDatas($sim);
        }

        return $this->render('notary/all_simulations.html.twig', [
            'pagination' => $pagination,
            'notary' => $notary
        ]);
    }

    #[Route('/notaire/configurer-ma-zone', name: 'notary_setup_zipcodes')]
    public function setupZipcodes(Request $request): Response
    {
        $notary = $this->getUser()->getNotary();
        $activeSub = $this->notaryService->getActiveSubscription($notary);
        $totalSlots = $this->offerService->getTotalAllowedSectors($notary, $activeSub);

        $initialCities = array_map(fn($sz) => $sz->getCity(), $notary->getSelectedZipCodes()->toArray());
        $form = $this->createForm(ZoneCoverageType::class, ['cities' => $initialCities], ['quota' => $totalSlots]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $rejected = $this->notaryService->updateZoneCoverage($notary, $form->get('cities')->getData(), $totalSlots);
            
            !empty($rejected) 
                ? $this->addFlash('warning', 'Certains secteurs sont complets : '.implode(', ', $rejected))
                : $this->addFlash('success', 'Zone de couverture mise à jour.');

            return $this->redirectToRoute('app_notary_dashboard');
        }

        return $this->render('notary/setup_zipcodes.html.twig', [
            'form' => $form->createView(),
            'totalSlots' => $totalSlots
        ]);
    }

    #[Route('/notaire/offres', name: 'app_notary_pricing')]
    public function pricing(): Response
    {
        if ($response = $this->checkNotaryStatus()) return $response;

        return $this->render('notary/pricing.html.twig', [
            'offers' => $this->offerRepository->findBy([
                'isAddon' => false,
                'isOnWebSite' => true
            ], ['baseNotariesCount' => 'ASC']),
        ]);
    }

    #[Route('/notaire/souscrire', name: 'app_notary_subscribe')]
    public function subscribe(Request $request): Response
    {
        if ($response = $this->checkNotaryStatus()) return $response;

        return $this->render('notary/subscribe_choice.html.twig', [
            'mainOffers' => $this->offerRepository->findBy(['isAddon' => false, 'isOnWebSite' => true]),
            'addons' => $this->offerRepository->findBy(['isAddon' => true, 'isOnWebSite' => true]),
            'preselectedId' => $request->query->get('preselect')
        ]);
    }

    #[Route('/notaire/verification', name: 'app_site_notary_pending_verification')]
    public function pendingVerification(): Response
    {
        $notary = $this->getUser()->getNotary();
        if ($notary && $notary->isConfirmed()) return $this->redirectToRoute('app_notary_dashboard');

        return $this->render('notary/pending_verification.html.twig');
    }

    #[Route('/notaire/mon-etude', name: 'app_notary_my_study')]
    public function myStudy(): Response
    {
        if ($this->getUser()->getNotary()) return $this->redirectToRoute('app_notary_dashboard');
        return $this->render('notary/complete_profile.html.twig');
    }
}