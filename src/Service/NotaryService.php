<?php

namespace App\Service;

use App\Entity\Notary;
use App\Entity\Subscription;
use App\Entity\Simulation;
use App\Repository\CityRepository;
use App\Repository\OfferRepository;
use App\Repository\SimulationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NotaryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SimulationRepository $simulationRepository,
        private SimulationService $simulationService,
        private CityRepository $cityRepository,
        private OfferRepository $offerRepository,
        private OfferService $offerService,
        private OptimizationService $optimizationService,
        #[Autowire('%kernel.environment%')] private string $env
    ) {}

    /**
     * Récupère l'abonnement actif ou un faux abonnement en DEV pour les tests
     */
    public function getActiveSubscription(Notary $notary): ?Subscription
    {
        $activeSub = $notary->getActiveSubscription();

        // Variable de test : mettre à 'true' pour simuler un notaire sans abonnement en DEV
        $forceNoSubscription = false;

        if ($forceNoSubscription) {
            return null;
        }

        // 1. Si un abonnement réel existe en base, on le retourne
        if ($activeSub) {
            return $activeSub;
        }

        // 2. Simulation uniquement en environnement de développement (DEV)
        if ($this->env === 'dev') {
            $simulatedSub = new Subscription();
            $simulatedSub->setNotary($notary);

            // On cherche par CODE technique plutôt que par NAME
            $baseOffer = $this->offerRepository->findOneBy(['code' => 'PACK_STANDARD']);

            // Si on a trouvé une offre, on simule l'abonnement
            if ($baseOffer) {
                $simulatedSub->setOffer($baseOffer);

                return $simulatedSub;
            }
        }

        return null;
    }

    /**
     * Récupère les simulations disponibles selon la zone du notaire
     */
    public function getAvailableSimulations(Notary $notary, int $limit = 10): array
    {
        $zipCodes = $this->getNotaryZipCodes($notary);

        if (!empty($zipCodes)) {
            $simulations = $this->simulationRepository->findByZipCodes($zipCodes, $notary, $limit);
        } else {
            $simulations = $this->simulationRepository->findLastInCountry($limit, $notary);
        }

        foreach ($simulations as $sim) {
            $this->injectOptimizationDatas($sim);
        }

        return $simulations;
    }

    /**
     * Injecte les totaux calculés dynamiquement dans l'objet simulation
     */
    public function injectOptimizationDatas(Simulation $sim): void
    {
        $datas = $this->optimizationService->getSimulationDatas(null, $sim->getUser());
        $sim->totalAvailable = $datas['totalAvailable'];
        $sim->totalSaving = $datas['totalSaving'];
    }

    /**
     * Récupère la liste brute des codes postaux sélectionnés par le notaire
     */
    public function getNotaryZipCodes(Notary $notary): array
    {
        return array_map(
            fn($sz) => $sz->getCity()->getPostalCode(),
            $notary->getSelectedZipCodes()->toArray()
        );
    }

    /**
     * Logique de mise à jour de la zone de couverture
     */
    public function updateZoneCoverage(Notary $notary, array $selectedCities, int $totalSlots): array
    {
        $rejectedCities = [];
        $finalCount = 0;

        // Nettoyage
        foreach ($notary->getSelectedZipCodes() as $oldZip) {
            $this->em->remove($oldZip);
        }
        $this->em->flush();

        foreach ($selectedCities as $city) {
            if ($finalCount >= $totalSlots) break;

            if ($city->getSelectedZipCodes()->count() < $city->getMaxNotariesCount()) {
                $selectedZip = new \App\Entity\SelectedZipCode();
                $selectedZip->setNotary($notary)->setCity($city);
                $this->em->persist($selectedZip);
                $finalCount++;
            } else {
                $rejectedCities[] = $city->getPostalCode();
            }
        }

        $this->em->flush();
        return $rejectedCities;
    }

    /**
     * Réserve une simulation pour un notaire
     */
    public function reserveSimulation(Simulation $simulation, Notary $notary): bool
    {
        if ($simulation->getReservedBy() !== null) {
            return false;
        }

        // 1. On lie le notaire à la simulation
        $simulation->setReservedBy($notary);
        $simulation->setReservedAt(new \DateTimeImmutable());

        // 2. On utilise ta nouvelle fonction pour créer l'étape 'RESERVED'
        // C'est cette ligne qui va déclencher le listener et le gain de points
        $this->simulationService->addStep($simulation, 'RESERVED', null, $notary); //! comme dans service.yaml

        // 3. On persiste les changements sur la simulation
        $this->em->persist($simulation);

        // 4. Le flush unique valide l'étape et la simulation
        $this->em->flush();

        return true;
    }

    public function getGroupedZips(Notary $notary): array
    {
        $groupedSectors = [];
        foreach ($notary->getSelectedZipCodes() as $sz) {
            $cp = $sz->getCity()->getPostalCode();
            if (!isset($groupedSectors[$cp])) {
                $groupedSectors[$cp] = $this->cityRepository->findBy(['postalCode' => $cp], ['name' => 'ASC']);
            }
        }

        return $groupedSectors;
    }
}
