<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\OfferService;
use App\Service\NotaryService;
use App\Repository\CityRepository;
use App\Repository\OfferRepository;
use App\Repository\NotaryRepository;
use App\Service\OptimizationService;
use App\Form\Notary\ZoneCoverageType;
use App\Form\Notary\NotaryProfileType;
use App\Repository\SimulationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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

        return $this->render('notary/dashboard.html.twig', [
            'notary' => $notary,
            'availableSimulations' => array_slice($availableSimulations, 0, 10),
            'stats' => [
                'totalSlots' => $this->offerService->getTotalAllowedSectors($notary, $activeSub),
                'usedSlots' => count($notary->getSelectedZipCodes()),
                'leadsInZone' => count($availableSimulations),
                'selectedLeads' => $notary->getSimulations()->count(),
            ],
            'viewAllSimulations' => count($availableSimulations) > 10,
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

        return $this->render('notary/folders/all_simulations.html.twig', [
            'pagination' => $pagination,
            'notary' => $notary
        ]);
    }

    // src/Controller/Notary/ZoneController.php

    #[Route('/notaire/configurer-ma-zone', name: 'app_notary_setup_zipcodes')]
    public function setupZipcodes(
        Request $request,
        NotaryService $notaryService, // Injection indispensable
        OfferService $offerService    // Injection indispensable
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();

        // 1. Sécurité Profil
        if (!$notary) {
            $this->addFlash('danger', 'Profil notaire introuvable.');
            return $this->redirectToRoute('app_account');
        }

        // 2. Blocage si pas d'abonnement (Réel ou Simulé en DEV)
        $activeSub = $notaryService->getActiveSubscription($notary);

        if (!$activeSub) {
            $this->addFlash('warning', 'Vous devez avoir un abonnement actif pour configurer votre zone.');
            return $this->redirectToRoute('app_notary_dashboard');
        }

        // 3. Logique métier liée au forfait
        $totalSlots = $offerService->getTotalAllowedSectors($notary, $activeSub);

        // Préparation du formulaire
        $initialCities = array_map(fn($sz) => $sz->getCity(), $notary->getSelectedZipCodes()->toArray());
        $form = $this->createForm(ZoneCoverageType::class, ['cities' => $initialCities], [
            'quota' => $totalSlots
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mise à jour via le service
            $rejected = $notaryService->updateZoneCoverage(
                $notary,
                $form->get('cities')->getData(),
                $totalSlots
            );

            if (!empty($rejected)) {
                $this->addFlash('warning', 'Certains secteurs sont complets : ' . implode(', ', $rejected));
            } else {
                $this->addFlash('success', 'Votre zone a été mise à jour en temps réel.');
            }

            return $this->redirectToRoute('app_notary_dashboard');
        }

        return $this->render('notary/setup_zipcodes.html.twig', [
            'form' => $form->createView(),
            'totalSlots' => $totalSlots,
            'activeSubscription' => $activeSub
        ]);
    }

    #[Route('/notaire/offres', name: 'app_notary_pricing')]
    public function pricing(): Response
    {
        if ($response = $this->checkNotaryStatus()) return $response;

        return $this->render('notary/subscription/pricing.html.twig', [
            'offers' => $this->offerRepository->findBy([
                'isAddon' => false,
                'isOnWebSite' => true
            ], ['baseNotariesCount' => 'ASC']),
        ]);
    }

    #[Route('/notaire/souscrire', name: 'app_notary_subscribe')]
    public function subscribe(Request $request, NotaryService $notaryService): Response
    {
        if ($response = $this->checkNotaryStatus()) return $response;

        /** @var Notary $notary */
        $notary = $this->getUser()->getNotary();
        $activeSub = $notaryService->getActiveSubscription($notary);

        // Si le notaire a déjà une offre active, on peut adapter la vue
        $hasMainOffer = ($activeSub !== null);

        return $this->render('notary/subscription/subscribe_choice.html.twig', [
            'mainOffers' => $this->offerRepository->findBy(['isAddon' => false, 'isOnWebSite' => true]),
            'addons' => $this->offerRepository->findBy(['isAddon' => true, 'isOnWebSite' => true]),
            'preselectedId' => $request->query->get('preselect'),
            'activeSubscription' => $activeSub,
            'hasMainOffer' => $hasMainOffer
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
    public function myStudy(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();

        // Sécurité : Si l'utilisateur n'a pas de profil Notaire du tout
        if (!$notary) {
            $this->addFlash('danger', 'Profil notaire introuvable.');
            return $this->redirectToRoute('app_account'); // Ou rediriger vers la création
        }

        // On utilise ton nouveau formulaire complet
        $form = $this->createForm(NotaryProfileType::class, $notary);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le setter dans Notary.php formatera le téléphone automatiquement
            $em->flush();

            $this->addFlash('success', 'Les informations de l\'étude ont été mises à jour avec succès.');

            // On reste sur la page pour voir les changements
            return $this->redirectToRoute('app_notary_my_study');
        }

        return $this->render('notary/complete_profile.html.twig', [
            'form' => $form->createView(),
            'notary' => $notary
        ]);
    }

    #[Route('/notaire/dossiers', name: 'app_notary_my_folders')]
    public function myfolders(
        NotaryService $notaryService, // <--- Injection indispensable
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();

        // 1. Sécurité : Profil Notaire existant
        if (!$notary) {
            $this->addFlash('danger', 'Profil notaire introuvable.');
            return $this->redirectToRoute('app_account');
        }

        // 2. Sécurité : Vérification de l'abonnement (Réel ou Simulé en DEV)
        $activeSub = $notaryService->getActiveSubscription($notary);

        if (!$activeSub) {
            $this->addFlash('warning', 'Vous devez avoir un abonnement actif pour voir vos dossiers.');
            return $this->redirectToRoute('app_notary_dashboard');
        }

        // 3. Accès autorisé
        return $this->render('notary/folders/my_folders.html.twig', [
            'folders' => $notary->getSimulations(),
            'notary' => $notary,
            'activeSubscription' => $activeSub // Utile pour afficher le nom du pack dans la vue
        ]);
    }
}
