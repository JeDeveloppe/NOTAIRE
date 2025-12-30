<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Offer;
use App\Entity\Notary;
use App\Entity\Person;
use App\Entity\Donation;
use App\Entity\OfferPrice;
use App\Entity\DonationRule;
use App\Entity\Relationship;
use App\Service\CityService;
use App\Entity\SimulationStatus;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CountryInitializerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:init')]
class AppInitCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private CountryInitializerService $countryInitializer, // Nouveau
        private CityRepository $cityRepository,
        private CityService $cityService, // Nouveau
        private array $relationships,
        private array $donationRules,
        private string $adminEmail,
        private string $adminPassword,
        private array $simulationStatuses,
        private array $offers
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Reset Database
        $io->title('Réinitialisation du système Notaire');
        $commands = [
            ['command' => 'doctrine:database:drop', '--force' => true, '--if-exists' => true],
            ['command' => 'doctrine:database:create'],
            ['command' => 'doctrine:schema:update', '--force' => true],
        ];
        foreach ($commands as $cmd) {
            $io->text('Exécution : <info>' . $cmd['command'] . '</info>');
            $this->getApplication()->find($cmd['command'])->run(new ArrayInput($cmd), $output);
        }

        // --- NOUVEAU : INITIALISATION GÉOGRAPHIQUE ---

        // 2. Initialisation des Pays
        $io->section('Initialisation des pays (FR, BE...)');
        $this->countryInitializer->initializeCountries($io);

        // 3. Import des Villes (France)
        $io->section('Importation du référentiel des villes');
        $this->cityService->importCitiesOfFrance($io);

        // ----------------------------------------------

        // 3. Import des simulations_status
        $io->section('Importation des statuts de simulation');
        foreach ($this->simulationStatuses as $code => $data) {
            $status = new SimulationStatus();

            // Le code (OPEN, RESERVED, etc.) est la clé du tableau dans services.yaml
            $status->setCode($code);
            $status->setLabel($data['label']);
            $status->setPoints($data['points']);
            $status->setDescription($data['description']);
            $status->setColor($data['color']);

            $this->em->persist($status);
            $io->text("-> Ajout du statut : <info>" . $data['label'] . "</info> (" . $data['points'] . " pts)");
        }

        // 4. Import des Relationships (anciennement étape 2)
        $io->section('Importation des liens de parenté');
        $createdRels = [];
        foreach ($this->relationships as $data) {
            $rel = new Relationship();
            $rel->setLabel($data['name']);
            $rel->setCode($data['code']);
            $this->em->persist($rel);
            $createdRels[$data['code']] = $rel;
            $io->text("-> Ajout du lien : " . $data['name']);
        }

        // 5. Import des DonationRules
        $io->section('Importation des règles fiscales');
        foreach ($this->donationRules as $data) {
            $rule = new DonationRule();
            $rule->setLabel($data['label']);
            $rule->setAllowanceAmount($data['amount']);
            $rule->setFrequencyYears($data['frequency']);
            $rule->setDonorMaxAge($data['donor_max_age']);
            $rule->setReceiverMinAge($data['receiver_min_age']);
            $rule->setIsCumulative($data['cumulative']);
            $rule->setIsBidirectional($data['is_bidirectional']);
            $rule->setTaxSystem($data['tax_system'] ?? 'tiers');
            $rule->setRelationship($createdRels[$data['relationship_code']] ?? null);
            $this->em->persist($rule);
            $io->text("-> Ajout de la règle : " . $data['label']);
        }

        // 6. Création Admin
        $io->section('Création de l\'administrateur');
        $admin = new User();
        $admin->setEmail($this->adminEmail);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, $this->adminPassword));
        $admin->setCity($this->cityRepository->findOneBy(['name' => 'Paris']));
        // Optionnel : $admin->setCity('Paris'); // Si tu veux tester le nouveau champ
        $this->em->persist($admin);
        $io->text("-> Admin créé : " . $this->adminEmail);

        // 7. Création d'une famille de test
        $io->section('Création de la famille Dubois');

        $gp = new Person();
        $gp->setFirstname('Robert')->setLastname('Dubois')->setGender('M')
            ->setBirthdate(new \DateTimeImmutable('1945-05-12'))->setOwner($admin);
        $this->em->persist($gp);

        $pere = new Person();
        $pere->setFirstname('Jean')->setLastname('Dubois')->setGender('M')
            ->setBirthdate(new \DateTimeImmutable('1972-10-25'))->setOwner($admin);
        $pere->addParent($gp);
        $this->em->persist($pere);

        $enfant = new Person();
        $enfant->setFirstname('Marc')->setLastname('Dubois')->setGender('M')
            ->setBirthdate(new \DateTimeImmutable('2002-01-10'))->setOwner($admin);
        $enfant->addParent($pere);
        $this->em->persist($enfant);

        $this->em->flush();

        // 8. Simulation d'une donation
        $io->section('Simulation d\'une donation de 80k€');
        $don = new Donation();
        $don->setDonor($pere);
        $don->setBeneficiary($enfant);
        $don->setAmount(80000);
        $don->setCreatedAt(new \DateTimeImmutable('-2 years'));
        $don->setDonateAt(new \DateTimeImmutable('-2 years'));
        $don->setType('progressif_direct');
        $don->setTaxPaid(0);

        $this->em->persist($don);

        // 9. Création d'un Notaire de test
        $io->section('Création du compte Notaire');

        $notaryUser = new User();
        $notaryUser->setEmail('notaire@exemple.com');
        $notaryUser->setRoles(['ROLE_NOTARY']);
        $notaryUser->setPassword($this->hasher->hashPassword($notaryUser, 'notaire123'));
        $notaryUser->setCity($this->cityRepository->findOneBy(['name' => 'Paris']));
        
        $this->em->persist($notaryUser);

        // On crée l'entité métier liée
        $notaryProfile = new Notary();
        $notaryProfile->setName('Étude de Maître Durand');
        $notaryProfile->setUser($notaryUser);
        $notaryProfile->setAddress('1 rue de la Paix');
        $notaryProfile->setCity($this->cityRepository->findOneBy(['name' => 'Paris']));
        $notaryProfile->setPhone('01 23 45 67 89');
        $notaryProfile->setSiret('12345678901234');
        $notaryProfile->setWebsite('https://exemple.com');

        $this->em->persist($notaryProfile);

        $io->text("-> Notaire créé : notaire@exemple.com");

        // --- ÉTAPE : IMPORT DES OFFRES ET TARIFS ---
        $io->section('Importation du catalogue des offres');

        foreach ($this->offers as $code => $data) {
            $offer = new Offer();
            $offer->setCode($code);
            $offer->setName($data['name']);
            $offer->setBaseSectorsCount($data['base_sectors']);
            $offer->setMaxNotariesPerSector($data['max_notaries_per_sector']);
            $offer->setIsAddon($data['is_addon']);
            $offer->setDescription($data['description']);
            $offer->setBadge($data['badge']);
            $offer->setIsOnWebSite($data['is_on_website']);

            $this->em->persist($offer);

            // On crée immédiatement le prix associé
            $price = new OfferPrice();
            $price->setOffer($offer);
            $price->setAmountHt($data['price_ht']);
            $price->setStartAt(new \DateTimeImmutable()); // Actif dès maintenant

            $this->em->persist($price);

            $io->text("-> Offre ajoutée : <info>" . $data['name'] . "</info> (" . ($data['price_ht'] / 100) . "€ HT)");
        }

        $this->em->flush();

        $io->success('Système complet initialisé avec géo-données !');

        return Command::SUCCESS;
    }
}
