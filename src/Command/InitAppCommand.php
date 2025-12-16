<?php

namespace App\Command;

use App\Entity\City;
use App\Entity\FiscalAbatementRule;
use App\Entity\TaxCatalog;
use App\Entity\TypeAct;
use App\Entity\User;
use App\Repository\CityRepository;
use App\Repository\FiscalAbatementRuleRepository;
use App\Service\ActService;
use App\Service\CityImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:init',
    description: 'Initialise les données de base (Villes, Utilisateur Admin, Règles Fiscales, Catalogues, Types d\'Actes).',
)]
class InitAppCommand extends Command
{
    // Déclarer les constantes pour les montants
    private const ABATEMENT_PARENT_ENFANT_CENTS = 10000000; // 100,000 €
    private const ABATEMENT_SARKOZY_CENTS = 3186500;    // 31,865 €

    private array $relationshipCodes;
    private string $adminEmail;
    private string $adminPassword;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $hasher,
        ParameterBagInterface $parameterBag,
        private readonly string $adminEmailParam, 
        private readonly string $adminPasswordParam, 
        private readonly FiscalAbatementRuleRepository $fiscalAbatementRuleRepository,
        private readonly CityImportService $cityImportService,
        private readonly CityRepository $cityRepository,
        string $name = null
    ) {
        parent::__construct($name);
        $this->relationshipCodes = $parameterBag->get('app.relationship_codes');
        // Assigner les valeurs des paramètres
        $this->adminEmail = $this->adminEmailParam;
        $this->adminPassword = $this->adminPasswordParam;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Démarrage de la commande d\'Initialisation des Données (app:init)');

        try {
            // ----------------------------------------------------
            $io->section('🌍 Importation des Villes (Pré-requis)');
            // ----------------------------------------------------
            $io->text("Appel du service d'importation des villes...");
            //$this->cityImportService->importCitiesOfFrance($io); // Passer $io pour l'affichage de la progression
            $io->success('Importation des villes terminée.');
            
            // ----------------------------------------------------
            $io->section('👤 Création de l\'Utilisateur de Base');
            // ----------------------------------------------------
            $this->createBaseUser($io);

            // ----------------------------------------------------
            $io->section('🧮 Création des Règles d\'Abattement Fiscal');
            // ----------------------------------------------------
            $this->createFiscalAbatementRules($this->entityManager);
            $io->note('Règles d\'Abattement créées/mises à jour.');

            // ----------------------------------------------------
            $io->section('📈 Création du Catalogue Fiscal (Taux)');
            // ----------------------------------------------------
            $this->createTaxCatalog($this->entityManager);
            $io->note('Catalogue Fiscal créé.');

            // ----------------------------------------------------
            $io->section('📜 Création des Types d\'Acte');
            // ----------------------------------------------------
            $this->createTypeActs($this->entityManager);
            $io->note('Types d\'Actes créés.');
            
            // ----------------------------------------------------
            $io->section('💾 Persistance Finale');
            // ----------------------------------------------------
            $io->text("Tentative de persistance finale (flush)...");
            $this->entityManager->flush(); 
            $io->success('✅ SUCCÈS : Toutes les données ont été persistées.');

        } catch (\Exception $e) {
            $io->error([
                '❌ ERREUR FATALE LORS DE L\'INITIALISATION :',
                "Message: " . $e->getMessage(),
                "Fichier: " . $e->getFile() . " à la ligne " . $e->getLine(),
            ]);
            // Re-lancer l'exception ou retourner Command::FAILURE
            return Command::FAILURE; 
        }

        return Command::SUCCESS;
    }

    /**
     * Crée l'utilisateur administrateur de base.
     */
    private function createBaseUser(SymfonyStyle $io): void
    {
        // RECHERCHE CORRIGÉE : Utilisation uniquement du code postal (14540)
        /** @var City|null $bourguebusCity */
        $bourguebusCity = $this->cityRepository->findOneBy(['postalCode' => '14540']); 
        
        if (!$bourguebusCity) {
            throw new \RuntimeException("L'entité City correspondant au code postal '14540' n'a pas été trouvée après l'importation. Le reste de la commande ne peut pas continuer.");
        }
        
        $user = new User();
        $user->setEmail($this->adminEmail); 
        $user->setPassword($this->hasher->hashPassword($user, $this->adminPassword));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setCity($bourguebusCity);
        $user->setIsActived(true);
        $this->entityManager->persist($user);
        
        $io->comment("Utilisateur Admin créé: **{$this->adminEmail}**");
    }


    /**
     * Crée et persiste les règles d'abattement.
     */
    private function createFiscalAbatementRules(EntityManagerInterface $manager): void
    {
        // Règle 1: Parent-Enfant (Classique)
        $rulePE = new FiscalAbatementRule();
        $rulePE->setCode('ABATTEMENT_CLASSIQUE_PE');
        // ⭐️ CORRECTION : Ajout de la description ⭐️
        $rulePE->setDescription('Abattement légal classique en ligne directe (parent/enfant).'); 
        // ⭐️ ---------------------------------- ⭐️
        $rulePE->setTypeOfLink('parent_enfant');
        $rulePE->setTypeOfAct(ActService::TYPE_ACT_DONATION);
        $rulePE->setAmountInCents(self::ABATEMENT_PARENT_ENFANT_CENTS);
        $rulePE->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS); 
        $rulePE->setMinBeneficiaryAge(0); // Assurer une valeur par défaut
        $rulePE->setMaxDonorAge(120);    // Assurer une valeur par défaut
        $manager->persist($rulePE);

        // Règle 6: Don Sarkozy (Art 790 G)
        $ruleSarko = new FiscalAbatementRule();
        $ruleSarko->setCode(ActService::CODE_SARKOZY_RULE);
        // ⭐️ CORRECTION : Ajout de la description ⭐️
        $ruleSarko->setDescription('Abattement exceptionnel pour les dons de sommes d\'argent (Article 790 G du CGI).'); 
        // ⭐️ ---------------------------------- ⭐️
        $ruleSarko->setTypeOfLink('descendants');
        $ruleSarko->setTypeOfAct(ActService::TYPE_ACT_DON_ARGENT_SEUL);
        $ruleSarko->setAmountInCents(self::ABATEMENT_SARKOZY_CENTS);
        $ruleSarko->setCycleOfYear(0); 
        $ruleSarko->setMinBeneficiaryAge(18); 
        $ruleSarko->setMaxDonorAge(80); 
        $manager->persist($ruleSarko);
    }

    /**
     * Crée et persiste le catalogue fiscal des taux.
     */
    private function createTaxCatalog(EntityManagerInterface $manager): void
    {
        $relationKeyMap = $this->createRelationKeyMap();

        foreach ($this->relationshipCodes as $name => $code) {
            
            if (isset($relationKeyMap[$code])) {
                $taxRule = new TaxCatalog();
                $taxRule->setRelationshipBetweenThe2People($code);
                $taxRule->setTaxRateLower(5); 
                $taxRule->setTaxRateUpper(45); 
                $manager->persist($taxRule);
            }
        }
    }

    /**
     * Crée et persiste les types d'actes.
     */
    private function createTypeActs(EntityManagerInterface $manager): void
    {
        $donationSimple = new TypeAct();
        $donationSimple->setName('Donation pleine propriété classique');
        $donationSimple->setCode(ActService::CODE_CLASSIQUE);
        $donationSimple->setIsTaxReductible(true);
        $donationSimple->setIsCyclical(true);
        $donationSimple->setFiscalRule('L\'abattement se reconstitue 15 ans après la date de chaque donation passée.');
        $donationSimple->setConditions('Aucune condition d\'âge spécifique.');
        $manager->persist($donationSimple);
    }
    
    /**
     * Mappage des codes de relation.
     */
    private function createRelationKeyMap(): array
    {
        return [
            $this->relationshipCodes['PARENT_ENFANT'] => 'parent_enfant',
            $this->relationshipCodes['GRAND_PARENT_PETIT_ENFANT'] => 'grand_parent_petit_enfant',
            $this->relationshipCodes['FRERE_SOEUR'] => 'frere_soeur',
            $this->relationshipCodes['ONCLE_NEVEU'] => 'oncle_neveu',
            $this->relationshipCodes['TIERS'] => 'tiers',
        ];
    }
}