<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\FiscalAbatementRule;
use App\Entity\TaxCatalog;
use App\Entity\TypeAct;
use App\Entity\User;
use App\Repository\CityRepository;
use App\Repository\FiscalAbatementRuleRepository;
use App\Service\ActService;
use App\Service\CityImportService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\Common\DataFixtures\FixtureGroupInterface;

class AppFixtures extends Fixture
{
    private array $relationshipCodes;
    private string $adminEmail;
    private string $adminPassword;
    // Déclarer les constantes pour les montants est souvent plus clair
    private const ABATEMENT_PARENT_ENFANT_CENTS = 10000000; // Exemple: 100,000 € en centimes
    private const ABATEMENT_SARKOZY_CENTS = 3186500;    // Exemple: 31,865 € en centimes

    public function __construct(
        private UserPasswordHasherInterface $hasher,
        ParameterBagInterface $parameterBag,
        string $adminEmail, 
        string $adminPassword,
        private readonly FiscalAbatementRuleRepository $fiscalAbatementRuleRepository,
        // INJECTIONS POUR L'IMPORT DES VILLES
        private readonly CityImportService $cityImportService,
        private readonly CityRepository $cityRepository
    )
    {
        $this->relationshipCodes = $parameterBag->get('app.relationship_codes');
        $this->adminEmail = $adminEmail;
        $this->adminPassword = $adminPassword;
    }

    public static function getGroups(): array
    {
        return ['init'];
    }
    
    public function load(ObjectManager $manager): void
    {
        // ----------------------------------------------------
        ## 🌍 Importation des Villes (Pré-requis)
        // ----------------------------------------------------
        
        dump("Début de l'appel au service d'importation des villes..."); 

        // Appel sans try/catch de haut niveau ici. Si une exception non gérée (par exemple SQL) survient 
        // durant le batching dans le service, elle sera propagée et stoppera les fixtures.
        $this->cityImportService->importCitiesOfFrance(null);
        
        dump("Service d'importation des villes terminé.");
        
        // ----------------------------------------------------
        ## 👤 Utilisateurs de Base
        // ----------------------------------------------------
        
        // Récupération de l'entité City (Assurez-vous que 'locationCity' ou 'city' est la bonne propriété dans User)
        /** @var City|null $bourguebusCity */
        $bourguebusCity = $this->cityRepository->findOneBy(['postalCode' => '14540', 'name' => 'Bourguébus']); 
        
        if (!$bourguebusCity) {
            // L'exception se propagera et annulera la transaction (comme le flush raté)
            throw new \RuntimeException("L'entité City 'Bourguébus (14540)' n'a pas été trouvée après l'importation. Le reste des fixtures ne peut pas continuer.");
        }
        
        $user = new User();
        $user->setEmail($this->adminEmail); 
        $user->setPassword($this->hasher->hashPassword($user, $this->adminPassword));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        
        // **Vérifiez si c'est 'setLocationCity' ou 'setCity' dans votre entité User.**
        $user->setCity($bourguebusCity);
        
        $user->setIsActived(true);
        $manager->persist($user);

        // ----------------------------------------------------
        ## 🧮 Règles d'Abattement Fiscal (FiscalAbatementRule)
        // ----------------------------------------------------
        $this->createFiscalAbatementRules($manager);
        
        // ----------------------------------------------------
        ## 📈 Catalogue Fiscal (TaxCatalog) - Taux
        // ----------------------------------------------------
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

        // ----------------------------------------------------
        ## 📜 Types d'Acte (TypeAct)
        // ----------------------------------------------------
        
        $donationSimple = new TypeAct();
        $donationSimple->setName('Donation pleine propriété classique');
        $donationSimple->setCode(ActService::CODE_CLASSIQUE);
        $donationSimple->setIsTaxReductible(true);
        $donationSimple->setIsCyclical(true);
        $donationSimple->setFiscalRule('L\'abattement se reconstitue 15 ans après la date de chaque donation passée.');
        $donationSimple->setConditions('Aucune condition d\'âge spécifique.');
        $manager->persist($donationSimple);

        // ----------------------------------------------------
        ## 💾 Persistance Finale (Diagnostic Crucial)
        // ----------------------------------------------------
        dump("Tentative de persistance finale (flush)...");
        try {
            $manager->flush(); 
            dump("✅ SUCCÈS : La persistance finale des fixtures est réussie.");
        } catch (\Exception $e) {
            dump("❌ ERREUR FATALE LORS DU FLUSH FINAL DES FIXTURES :");
            dump("Message: " . $e->getMessage()); 
            
            // Re-lancer l'exception pour que Symfony stoppe correctement la commande et affiche l'erreur en détail
            throw $e; 
        }
    }

    /**
     * Crée et persiste les règles d'abattement dans l'entité FiscalAbatementRule.
     */
    private function createFiscalAbatementRules(ObjectManager $manager): void
    {
        // Règle 1: Parent-Enfant (Classique)
        $rule = new FiscalAbatementRule();
        $rule->setCode('ABATTEMENT_CLASSIQUE_PE');
        $rule->setTypeOfLink('parent_enfant');
        $rule->setTypeOfAct(ActService::TYPE_ACT_DONATION);
        $rule->setAmountInCents(self::ABATEMENT_PARENT_ENFANT_CENTS);
        $rule->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS); 
        $manager->persist($rule);

        // Règle 6: Don Sarkozy (Art 790 G)
        $rule = new FiscalAbatementRule();
        $rule->setCode(ActService::CODE_SARKOZY_RULE);
        $rule->setTypeOfLink('descendants');
        $rule->setTypeOfAct(ActService::TYPE_ACT_DON_ARGENT_SEUL);
        $rule->setAmountInCents(self::ABATEMENT_SARKOZY_CENTS);
        $rule->setCycleOfYear(0); // 0 pour un abattement non cyclique
        $rule->setMinBeneficiaryAge(18); 
        $rule->setMaxDonorAge(80); 
        $manager->persist($rule);
    }
    
    private function createRelationKeyMap(): array
    {
        // Mappage des codes internes de relation avec les clés de l'abattement
        return [
            $this->relationshipCodes['PARENT_ENFANT'] => 'parent_enfant',
            $this->relationshipCodes['GRAND_PARENT_PETIT_ENFANT'] => 'grand_parent_petit_enfant',
            $this->relationshipCodes['FRERE_SOEUR'] => 'frere_soeur',
            $this->relationshipCodes['ONCLE_NEVEU'] => 'oncle_neveu',
            $this->relationshipCodes['TIERS'] => 'tiers',
        ];
    }
}