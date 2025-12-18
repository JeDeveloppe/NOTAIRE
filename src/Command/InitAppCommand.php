<?php

namespace App\Command;

use App\Entity\City;
use App\Entity\User;
use App\Entity\TypeAct;
use App\Entity\TaxCatalog;
use App\Service\ActService;
use App\Entity\SubscriptionType;
use App\Repository\CityRepository;
use App\Service\CityImportService;
use App\Entity\FiscalAbatementRule;
use App\Entity\SubdivisionIndicator; 
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use App\Repository\FiscalAbatementRuleRepository;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:init',
    description: 'Initialise les données de base (Villes, Utilisateur Admin, Règles Fiscales, Catalogues, Types d\'Actes, Indicateurs de Subdivision).',
)]
class InitAppCommand extends Command
{
    // Déclarer les constantes pour les montants
    private const ABATEMENT_PARENT_ENFANT_CENTS = 10000000; // 100,000 €
    private const ABATEMENT_SARKOZY_CENTS = 3186500;    // 31,865 €

    // Liste complète des indicateurs
    private const SUBDIVISION_INDICATORS = ['bis', 'ter', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

    private array $relationshipCodes;
    private string $adminEmail;
    private string $adminPassword;
    private int $defaultRadius;

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
        $this->defaultRadius = $parameterBag->get('app.default_notary_radius');
        $this->adminEmail = $this->adminEmailParam;
        $this->adminPassword = $this->adminPasswordParam;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Démarrage de la commande d\'Initialisation des Données (app:init)');

        try {
            // ... (Sections inchangées : Villes et Indicateurs) ...
            $io->section('🌍 Importation des Villes (Pré-requis)');
            $io->text("Appel du service d'importation des villes...");
            //$this->cityImportService->importCitiesOfFrance($io);
            $io->success('Importation des villes terminée.');

            $io->section('💳 Création des Types d\'Abonnement (Standard, Premium)');
            // ----------------------------------------------------
            $this->createSubscriptionTypes($this->entityManager);
            $io->note('Types d\'abonnement créés/mis à jour.');
            
            $io->section('🏠 Création des Indicateurs de Subdivision (bis, ter, etc.)');
            $this->createSubdivisionIndicators($this->entityManager); 
            $io->note('Indicateurs de Subdivision créés.');

            // ----------------------------------------------------
            $io->section('👤 Création/Mise à jour de l\'Utilisateur Admin');
            // ----------------------------------------------------
            $this->createBaseUser($io);

            // ----------------------------------------------------
            $io->section('🧮 Création/Mise à jour des Règles d\'Abattement Fiscal');
            // ----------------------------------------------------
            $this->createFiscalAbatementRules($this->entityManager);
            $io->note('Règles d\'Abattement créées/mises à jour.');

            // ----------------------------------------------------
            $io->section('📈 Création/Mise à jour du Catalogue Fiscal (Taux)');
            // ----------------------------------------------------
            $this->createTaxCatalog($this->entityManager);
            $io->note('Catalogue Fiscal créé/mis à jour.');

            // ----------------------------------------------------
            $io->section('📜 Création/Mise à jour des Types d\'Acte');
            // ----------------------------------------------------
            $this->createTypeActs($this->entityManager);
            $io->note('Types d\'Actes créés/mis à jour.');
            
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
            return Command::FAILURE; 
        }

        return Command::SUCCESS;
    }


    /**
     * Crée et persiste les indicateurs de subdivision. (Méthode déjà idempotente)
     */
    private function createSubdivisionIndicators(EntityManagerInterface $manager): void
    {
        foreach (self::SUBDIVISION_INDICATORS as $indicatorName) {
            // Utiliser le repository pour vérifier si l'indicateur existe déjà
            $existingIndicator = $manager->getRepository(SubdivisionIndicator::class)->findOneBy(['name' => $indicatorName]);
            
            if (!$existingIndicator) {
                $indicator = new SubdivisionIndicator();
                $indicator->setName($indicatorName);
                $manager->persist($indicator);
            }
        }
    }


    /**
     * Crée ou met à jour l'utilisateur administrateur de base.
     */
    private function createBaseUser(SymfonyStyle $io): void
    {
        $bourguebusCity = $this->cityRepository->findOneBy(['postalCode' => '14540']); 
        
        if (!$bourguebusCity) {
            throw new \RuntimeException("L'entité City correspondant au code postal '14540' n'a pas été trouvée après l'importation.");
        }

        // ⭐️ VÉRIFICATION D'EXISTENCE PAR EMAIL ⭐️
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->adminEmail]);
        
        if(!$user) {
            $user = new User();
            $io->comment("Création de l'utilisateur Admin.");
        } else {
            $io->comment("Mise à jour de l'utilisateur Admin existant.");
        }
        
        $user->setEmail($this->adminEmail); 
        // L'utilisateur est mis à jour avec le nouveau mot de passe/rôles/ville à chaque exécution
        $user->setPassword($this->hasher->hashPassword($user, $this->adminPassword));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setCity($bourguebusCity);
        $user->setIsActived(true);
        $this->entityManager->persist($user);
        
        $io->comment("Utilisateur Admin : **{$this->adminEmail}**");
    }


    /**
     * Crée ou met à jour les règles d'abattement.
     */
    private function createFiscalAbatementRules(EntityManagerInterface $manager): void
    {
        $repo = $manager->getRepository(FiscalAbatementRule::class);
        
        // Règle 1: Parent-Enfant (Classique)
        $codePE = 'ABATTEMENT_CLASSIQUE_PE';
        $rulePE = $repo->findOneBy(['code' => $codePE]);
        if (!$rulePE) {
            $rulePE = new FiscalAbatementRule();
            $rulePE->setCode($codePE);
        }
        $rulePE->setDescription('Abattement légal classique en ligne directe (parent/enfant).'); 
        $rulePE->setTypeOfLink('parent_enfant');
        $rulePE->setTypeOfAct(ActService::TYPE_ACT_DONATION);
        $rulePE->setAmountInCents(self::ABATEMENT_PARENT_ENFANT_CENTS);
        $rulePE->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS); 
        $rulePE->setMinBeneficiaryAge(0);
        $rulePE->setMaxDonorAge(120);
        $manager->persist($rulePE);

        // Règle 6: Don Sarkozy (Art 790 G)
        $codeSarko = ActService::CODE_SARKOZY_RULE;
        $ruleSarko = $repo->findOneBy(['code' => $codeSarko]);
        if (!$ruleSarko) {
            $ruleSarko = new FiscalAbatementRule();
            $ruleSarko->setCode($codeSarko);
        }
        $ruleSarko->setDescription('Abattement exceptionnel pour les dons de sommes d\'argent (Article 790 G du CGI).'); 
        $ruleSarko->setTypeOfLink('descendants');
        $ruleSarko->setTypeOfAct(ActService::TYPE_ACT_DON_ARGENT_SEUL);
        $ruleSarko->setAmountInCents(self::ABATEMENT_SARKOZY_CENTS);
        $ruleSarko->setCycleOfYear(0); 
        $ruleSarko->setMinBeneficiaryAge(18); 
        $ruleSarko->setMaxDonorAge(80); 
        $manager->persist($ruleSarko);
    }

    /**
     * Crée ou met à jour le catalogue fiscal des taux.
     */
    private function createTaxCatalog(EntityManagerInterface $manager): void
    {
        $repo = $manager->getRepository(TaxCatalog::class);
        $relationKeyMap = $this->createRelationKeyMap();

        foreach ($this->relationshipCodes as $name => $code) {
            
            if (isset($relationKeyMap[$code])) {
                // ⭐️ VÉRIFICATION D'EXISTENCE PAR LE CODE DE RELATION ⭐️
                $taxRule = $repo->findOneBy(['relationshipBetweenThe2People' => $code]);

                if (!$taxRule) {
                    $taxRule = new TaxCatalog();
                    $taxRule->setRelationshipBetweenThe2People($code);
                }

                // Mise à jour des valeurs pour chaque exécution
                $taxRule->setTaxRateLower(5); 
                $taxRule->setTaxRateUpper(45); 
                $manager->persist($taxRule);
            }
        }
    }

    /**
     * Crée ou met à jour les types d'actes.
     */
    private function createTypeActs(EntityManagerInterface $manager): void
    {
        $repo = $manager->getRepository(TypeAct::class);
        $codeDonation = ActService::CODE_CLASSIQUE;
        
        // ⭐️ VÉRIFICATION D'EXISTENCE PAR LE CODE D'ACTE ⭐️
        $donationSimple = $repo->findOneBy(['code' => $codeDonation]);
        
        if (!$donationSimple) {
            $donationSimple = new TypeAct();
            $donationSimple->setCode($codeDonation);
        }
        
        // Mise à jour des valeurs pour chaque exécution
        $donationSimple->setName('Donation pleine propriété classique');
        $donationSimple->setIsTaxReductible(true);
        $donationSimple->setIsCyclical(true);
        $donationSimple->setFiscalRule('L\'abattement se reconstitue 15 ans après la date de chaque donation passée.');
        $donationSimple->setConditions('Aucune condition d\'âge spécifique.');
        $manager->persist($donationSimple);
    }
    
    /**
     * Mappage des codes de relation (Inchangé).
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

    private function createSubscriptionTypes(EntityManagerInterface $manager): void
    {
        $repo = $manager->getRepository(SubscriptionType::class);

        // Définition des plans
        $plans = [
            [
                'name' => 'Standard',
                'duration' => 0, // 0 ou null pour illimité/gratuit
                'maxRadius' => $this->defaultRadius, // Rayon max par défaut
                'price' => 0
            ],
            [
                'name' => 'Premium',
                'duration' => 12, // Durée en mois par défaut
                'maxRadius' => 100, // Rayon max pour Premium
                'price' => 4999 // Prix en centimes (49.99 €)
            ],
        ];

        foreach ($plans as $planData) {
            $plan = $repo->findOneBy(['name' => $planData['name']]);
            
            if (!$plan) {
                $plan = new SubscriptionType();
                $plan->setName($planData['name']);
            }
            
            $plan->setDuration($planData['duration']);
            $manager->persist($plan);
        }
    }
}