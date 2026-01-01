<?php

namespace App\Controller;

use App\Entity\Notary;
use App\Entity\Simulation;
use App\Entity\Subscription;
use App\Service\OfferService;
use App\Repository\CityRepository;
use App\Repository\OfferRepository;
use App\Service\OptimizationService;
use App\Form\Notary\ZoneCoverageType;
use App\Repository\SimulationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class NotaryController extends AbstractController
{
    public function __construct(
        private SimulationRepository $simulationRepository,
        private OfferRepository $offerRepository,
        private CityRepository $cityRepository,
        #[Autowire('%kernel.environment%')] private string $env, // <-- N'oublie pas 'private' ici !
        private OptimizationService $optimizationService
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

    #[Route('/notaire/simulations/{page}', name: 'app_site_notary_all_simulations', defaults: ['page' => 1])]
    public function simulations(int $page, PaginatorInterface $paginator, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();
        // On récupère les entités de codes postaux sélectionnés
        $selectedZipEntities = $notary->getSelectedZipCodes();

        $zipCodes = [];
        foreach ($selectedZipEntities as $sz) {
            // On extrait la chaîne de caractères du code postal comme dans ton dashboard
            $zipCodes[] = $sz->getCity()->getPostalCode();
        }

        // 1. On récupère la Query (et non le résultat) pour que le paginateur puisse ajouter le LIMIT/OFFSET
        $query = $this->simulationRepository->getQueryByZipCodes($zipCodes, $notary);

        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $page,  /* numéro de page */
            20,      /* limite par page (Ton livre de 20 pages) */
            ['distinct' => true]
        );
        // 2. On injecte les datas d'optimisation (Potentiel fiscal) dans les résultats de la page actuelle
        foreach ($pagination->getItems() as $sim) {
            $optimizationDatas = $this->optimizationService->getSimulationDatas(null, $sim->getUser());
            $sim->totalAvailable = $optimizationDatas['totalAvailable'];
            $sim->totalSaving = $optimizationDatas['totalSaving'];
        }

        $testResults = $query->setMaxResults(10)->getResult();

        return $this->render('notary/all_simulations.html.twig', [
            'pagination' => $pagination,
            'notary' => $notary
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
    // src/Controller/Notary/DashboardController.php

    #[Route('/notaire', name: 'app_notary_dashboard')]
    public function dashboard(
        OfferService $offerService,
        SimulationRepository $simulationRepository
    ): Response {
        if ($response = $this->checkNotaryStatus()) {
            return $response;
        }

        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();

        // 1. Gestion de l'abonnement
        $activeSub = $this->getActiveSubWithSimulation($notary);
        $hasActiveOffer = ($activeSub !== null);

        // 2. Initialisation des données de zone
        $groupedSectors = [];
        $zipCodes = [];

        if ($hasActiveOffer) {
            // On récupère les secteurs sélectionnés par le notaire
            $selectedZipEntities = $notary->getSelectedZipCodes();

            foreach ($selectedZipEntities as $sz) {
                $cp = $sz->getCity()->getPostalCode();
                $zipCodes[] = $cp;

                // On regroupe toutes les villes partageant ce code postal pour l'accordéon
                if (!isset($groupedSectors[$cp])) {
                    $groupedSectors[$cp] = $this->cityRepository->findBy(
                        ['postalCode' => $cp],
                        ['name' => 'ASC']
                    );
                }
            }
            $availableSimulations = $simulationRepository->findByZipCodes($zipCodes, $notary, 10); //limite de 10 sur le dashboard
        } else {
            $availableSimulations = $simulationRepository->findLastInCountry(10, $notary);
        }
        ksort($groupedSectors);

        foreach ($availableSimulations as $sim) {
            // On récupère les données d'optimisation pour l'utilisateur de la simulation
            $optimizationDatas = $this->optimizationService->getSimulationDatas(null, $sim->getUser());

            // On injecte dynamiquement les totaux dans l'objet simulation pour Twig
            $sim->totalAvailable = $optimizationDatas['totalAvailable'];
            $sim->totalSaving = $optimizationDatas['totalSaving'];
        }

        // Si plus de 10, on affiche le bouton sur le dashboard
        if (count($availableSimulations) > 10) {
            $viewAllSimulations = true;
        } else {
            $viewAllSimulations = false;
        }

        // 3. Statistiques
        $totalSlots = $offerService->getTotalAllowedSectors($notary, $activeSub);
        $usedSlots = count($zipCodes);

        return $this->render('notary/dashboard.html.twig', [
            'notary' => $notary,
            'hasActiveOffer' => $hasActiveOffer,
            'activeSubscription' => $activeSub,
            'availableSimulations' => $availableSimulations,
            'groupedSectors' => $groupedSectors, // Transmis au Twig
            'stats' => [
                'totalSlots' => $totalSlots,
                'usedSlots' => $usedSlots,
                'percentage' => ($totalSlots > 0) ? min(100, ($usedSlots / $totalSlots) * 100) : 0,
                'leadsInZone' => count($availableSimulations),
                'selectedLeads' => $notary->getSimulations()->count(),
            ],
            'viewAllSimulations' => $viewAllSimulations
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
    public function pricing(): Response
    {
        if ($response = $this->checkNotaryStatus()) {
            return $response;
        }

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
        if ($response = $this->checkNotaryStatus()) {
            return $response;
        }

        $preselectedId = $request->query->get('preselect');
        $mainOffers = $this->offerRepository->findBy(['isAddon' => false, 'isOnWebSite' => true]);
        $addons = $this->offerRepository->findBy(['isAddon' => true, 'isOnWebSite' => true]);

        return $this->render('notary/subscribe_choice.html.twig', [
            'mainOffers' => $mainOffers,
            'addons' => $addons,
            'preselectedId' => $preselectedId
        ]);
    }

    // src/Controller/Notary/DashboardController.php

    #[Route('/notaire/configurer-ma-zone', name: 'notary_setup_zipcodes')]
    public function setupZipcodes(
        Request $request,
        OfferService $offerService,
        EntityManagerInterface $em,
        CityRepository $cityRepository // Injecter le repository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $notary = $user->getNotary();

        if (!$notary) {
            throw $this->createNotFoundException("Profil notaire introuvable.");
        }

        $activeSub = $this->getActiveSubWithSimulation($notary);
        $totalSlots = $offerService->getTotalAllowedSectors($notary, $activeSub);

        $initialCities = [];
        foreach ($notary->getSelectedZipCodes() as $selectedZip) {
            if ($selectedZip->getCity()) {
                $initialCities[] = $selectedZip->getCity();
            }
        }

        $form = $this->createForm(ZoneCoverageType::class, ['cities' => $initialCities], [
            'quota' => $totalSlots
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedCities = $form->get('cities')->getData();
            $finalCount = 0;
            $rejectedCities = [];

            // 1. Nettoyage des anciens secteurs
            foreach ($notary->getSelectedZipCodes() as $oldSelectedZip) {
                $em->remove($oldSelectedZip);
            }
            // On flush ici pour libérer les places du notaire actuel avant de re-vérifier
            $em->flush();

            foreach ($selectedCities as $city) {
                // 2. Vérification du quota de l'offre (Slots de l'abonnement)
                if ($finalCount >= $totalSlots) break;

                // 3. Vérification du quota de la ville (Places disponibles dans la ville)
                // On compte combien de SelectedZipCode existent pour cette ville
                $currentOccupancy = $city->getSelectedZipCodes()->count();

                if ($currentOccupancy < $city->getMaxNotariesCount()) {
                    $selectedZip = new \App\Entity\SelectedZipCode();
                    $selectedZip->setNotary($notary);
                    $selectedZip->setCity($city);
                    $em->persist($selectedZip);
                    $finalCount++;
                } else {
                    $rejectedCities[] = $city->getPostalCode();
                }
            }

            $em->flush();

            // Gestion des messages de retour
            if (!empty($rejectedCities)) {
                $this->addFlash('warning', sprintf(
                    'Certains secteurs (%s) ont été rejetés car ils sont désormais complets.',
                    implode(', ', $rejectedCities)
                ));
            } else {
                $this->addFlash('success', 'Zone de couverture mise à jour.');
            }

            return $this->redirectToRoute('app_notary_dashboard');
        }

        return $this->render('notary/setup_zipcodes.html.twig', [
            'form' => $form->createView(),
            'totalSlots' => $totalSlots,
        ]);
    }

    private function getActiveSubWithSimulation(Notary $notary): ?Subscription
    {
        $activeSub = $notary->getActiveSubscription();

        // Change cette variable manuellement pour tester les deux états
        $forceNoSubscription = false;

        if ($forceNoSubscription) {
            return null;
        }

        if (!$activeSub && $this->env === 'dev') {
            $activeSub = new Subscription();
            $activeSub->setNotary($notary);

            $baseOffer = $this->offerRepository->findOneBy(['name' => 'PACK_STANDARD'])
                ?? $this->offerRepository->findOneBy([]);

            if ($baseOffer) {
                $activeSub->setOffer($baseOffer);
            }
        }

        return $activeSub;
    }

    #[Route('/notaire/simulation/{reference}', name: 'app_notary_simulation_view')]
public function viewSimulationByNotary(
    OptimizationService $optimizationService, 
    string $reference
): Response {
    /** @var User $user */
    $user = $this->getUser();
    $notary = $user->getNotary();

    if (!$notary) {
        throw $this->createNotFoundException("Profil notaire introuvable.");
    }

    // 1. Recherche de la simulation par son code (référence)
    $simulation = $this->simulationRepository->findOneBy(['reference' => $reference]);

    if (!$simulation) {
        throw $this->createNotFoundException("Dossier introuvable.");
    }

    // 2. SÉCURITÉ : Vérifier si le notaire a le droit de voir ce dossier
    $client = $simulation->getUser();
    $clientZip = $client->getCity()->getPostalCode();
    
    // Récupération des codes postaux du notaire pour vérification
    $notaryZips = array_map(fn($z) => $z->getCity()->getPostalCode(), $notary->getSelectedZipCodes()->toArray());

    // Le notaire accède au dossier si : il l'a déjà réservé OU si le dossier est OPEN dans sa zone
    $isOwner = ($simulation->getReservedBy() === $notary);
    $isInZone = in_array($clientZip, $notaryZips) && $simulation->getReservedBy() === null;

    if (!$isOwner && !$isInZone) {
        throw $this->createAccessDeniedException("Ce dossier n'est pas dans votre zone de couverture.");
    }

    // 3. Génération des analyses via ton OptimizationService
    $analysis = $optimizationService->getDonationAnalyses($client);
    $familyPlan = $optimizationService->getGlobalFamilyPlan($client);

    return $this->render('notary/simulation_view.html.twig', [
        'simulation' => $simulation,
        'client' => $client,
        'analysis' => $analysis,
        'familyPlan' => $familyPlan,
        'isOwner' => $isOwner,
        'notary' => $notary
    ]);
}

#[Route('/notaire/simulation/reserve/{code}', name: 'app_notary_reserve_simulation')]
public function reserve(string $code, EntityManagerInterface $em, Request $request): Response
{
    /** @var User $user */
    $user = $this->getUser();
    $notary = $user->getNotary();

    if (!$notary) {
        throw $this->createNotFoundException("Profil notaire introuvable.");
    }

    // 1. On récupère la simulation par son code
    $simulation = $this->simulationRepository->findOneBy(['reference' => $code]);

    if (!$simulation) {
        throw $this->createNotFoundException("Dossier introuvable.");
    }

    // 2. Vérification : est-il déjà réservé ?
    if ($simulation->getReservedBy() !== null) {
        $this->addFlash('danger', 'Ce dossier a déjà été sélectionné par un autre office.');
        return $this->redirectToRoute('app_site_notary_all_simulations');
    }

    dd('STOP POUR CE SOIR: faire aussi l\'attribution des points au notaire');

    // 3. Attribution et Changement de Statut
    // On récupère le statut "RESERVED" ou "COLLECTED" (adapte selon tes codes de statut)
    $statusReserved = $em->getRepository(SimulationStatus::class)->findOneBy(['code' => 'RESERVED']);

    $simulation->setReservedBy($notary);
    $simulation->setReservedAt(new \DateTime()); // Optionnel : si tu as ce champ pour calculer les 15 jours
    if ($statusReserved) {
        $simulation->setStatus($statusReserved);
    }

    $em->flush();

    // 4. Message de succès et redirection
    $this->addFlash('success', 'Dossier récupéré avec succès !');

    // 1. On identifie la provenance (par défaut 'registre')
    $from = $request->query->get('from');
    
    // 2. On prépare la route de retour
    $targetRoute = ($from === 'dashboard') 
        ? 'app_site_notary_dashboard' 
        : 'app_site_notary_all_simulations';

    $this->addFlash('success', 'Dossier #'.$code.' ajouté avec succès à votre étude.');

    // 3. On redirige vers l'origine
    return $this->redirectToRoute($targetRoute);
}
}
