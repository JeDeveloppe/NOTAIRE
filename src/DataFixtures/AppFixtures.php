<?php

namespace App\DataFixtures;

use App\Entity\FiscalAbatementRule;
use App\Entity\TaxCatalog;
use App\Entity\TypeAct;
use App\Entity\User;
use App\Repository\FiscalAbatementRuleRepository;
use App\Service\ActService; // <--- S'assurer que les constantes fiscales sont là
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface as FixturesBundleFixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\Common\DataFixtures\FixtureGroupInterface;

class AppFixtures extends Fixture
{
    // ... (Propriétés et Constructeur inchangés - car ils sont propres) ...
    private array $relationshipCodes;
    private string $adminEmail;
    private string $adminPassword;

    public function __construct(
        private UserPasswordHasherInterface $hasher,
        ParameterBagInterface $parameterBag,
        string $adminEmail, 
        string $adminPassword,
        private readonly FiscalAbatementRuleRepository $fiscalAbatementRuleRepository 
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
        // 1. UTILISATEURS DE BASE
        // ----------------------------------------------------
        $user = new User();
        $user->setEmail($this->adminEmail); 
        $user->setPassword($this->hasher->hashPassword($user, $this->adminPassword));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setPostalCode(14540); // Code postal fictif pour l'admin
        $user->setCity('Bourguébus'); // Ville fictive pour l'admin
        $user->setIsActived(true);
        $manager->persist($user);

        // ----------------------------------------------------
        // 2. RÈGLES D'ABATTEMENT FISCAL (FiscalAbatementRule)
        // ----------------------------------------------------
        $this->createFiscalAbatementRules($manager);
        
        // ----------------------------------------------------
        // 3. CATALOGUE FISCAL (TaxCatalog) - Taux
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
        // 4. TYPES D'ACTE (TypeAct)
        // ----------------------------------------------------
        // (Codes des TypeAct doivent correspondre aux codes utilisés dans ActService)
        
        // Type 1 : CLASSIQUE Pleine Propriété
        $donationSimple = new TypeAct();
        $donationSimple->setName('Donation pleine propriété classique');
        $donationSimple->setCode(ActService::CODE_CLASSIQUE); // Utilisation de ActService
        $donationSimple->setIsTaxReductible(true);
        $donationSimple->setIsCyclical(true);
        $donationSimple->setFiscalRule('L\'abattement se reconstitue 15 ans après la date de chaque donation passée. Le montant de l\'abattement dépend du lien de parenté (parent/enfant, grand-parent/petit-enfant, etc.).');
        $donationSimple->setConditions('Aucune condition d\'âge spécifique. Applicable aux donations de biens ou de sommes d\'argent.');
        $manager->persist($donationSimple);
        
        // Type 2 : USUFRUIT
        $donationUsufruit = new TypeAct();
        $donationUsufruit->setName('Donation usufruit');
        $donationUsufruit->setCode(ActService::CODE_USUFRUIT ?? 'USUFRUIT'); // Utilisation de ActService
        $donationUsufruit->setIsTaxReductible(true);
        $donationUsufruit->setIsCyclical(true);
        $donationUsufruit->setFiscalRule('Règles similaires à la donation classique, mais la valeur taxable est déterminée par l\'âge de l\'usufruitier.');
        $donationUsufruit->setConditions('Nécessite une évaluation de la valeur de l\'usufruit selon le barème fiscal en vigueur.');
        $manager->persist($donationUsufruit);

        // Type 3 : SARKOZY (Don familial de sommes d'argent)
        $donationSarkozy = new TypeAct();
        $donationSarkozy->setName('Donation familiale d\'argent (Sarkozy)');
        $donationSarkozy->setCode(ActService::CODE_SARKOZY); // Utilisation de ActService
        $donationSarkozy->setIsTaxReductible(true);
        $donationSarkozy->setIsCyclical(false); 
        $donationSarkozy->setFiscalRule('C\'est une enveloppe d\'abattement forfaitaire unique et non renouvelable qui se cumule avec l\'abattement classique. Elle est utilisée en priorité.');
        $donationSarkozy->setConditions(
            '1. Le donateur doit avoir moins de 80 ans au jour du don. 
             2. Le bénéficiaire doit être majeur ou émancipé. 
             3. Applicable uniquement aux dons de sommes d\'argent (virement, chèque, espèces).'
        );
        $manager->persist($donationSarkozy);
        
        // ----------------------------------------------------
        // Finalisation
        // ----------------------------------------------------
        $manager->flush();
    }

    /**
     * Crée et persiste les règles d'abattement dans la nouvelle entité FiscalAbatementRule.
     */
    private function createFiscalAbatementRules(ObjectManager $manager): void
    {
        // Montants en centimes pour les Fixtures
        $ABATEMENT_AMOUNTS = [
            'parent_enfant' => 10000000, // 100 000 €
            'grand_parent_petit_enfant' => 3186500, // 31 865 €
            'epoux' => 8072400, // 80 724 €
            'frere_soeur' => 1593200, // 15 932 €
            'neveu_niece' => 796700, // 7 967 €
            'tiers' => 159400, // 1 594 € (Abattement pour les autres liens, ex: concubin, cousin)
            'don_sarkozy_cumulable' => 3186500, // 31 865 €
        ];

        // Règle 1: Parent-Enfant (Classique)
        $rule = new FiscalAbatementRule();
        $rule->setCode('ABATTEMENT_CLASSIQUE_PE');
        $rule->setDescription('Abattement en ligne directe Parent vers Enfant');
        $rule->setTypeOfLink('parent_enfant');
        $rule->setTypeOfAct(ActService::TYPE_ACT_DONATION); // Utiliser la constante du service
        $rule->setAmountInCents($ABATEMENT_AMOUNTS['parent_enfant']);
        $rule->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS); 
        $rule->setMinBeneficiaryAge(0); 
        $rule->setMaxDonorAge(200); 
        $manager->persist($rule);

        // Règle 2: Grand-Parent-Petit-Enfant (Classique)
        $rule = new FiscalAbatementRule();
        $rule->setCode('ABATTEMENT_CLASSIQUE_GPPE');
        $rule->setDescription('Abattement en ligne directe Grand-Parent vers Petit-Enfant');
        $rule->setTypeOfLink('grand_parent_petit_enfant');
        $rule->setTypeOfAct(ActService::TYPE_ACT_DONATION);
        $rule->setAmountInCents($ABATEMENT_AMOUNTS['grand_parent_petit_enfant']);
        $rule->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS);
        $rule->setMinBeneficiaryAge(0); 
        $rule->setMaxDonorAge(200); 
        $manager->persist($rule);

        // Règle 3: Époux/PACS (Classique)
        $rule = new FiscalAbatementRule();
        $rule->setCode('ABATTEMENT_CLASSIQUE_EPOUX');
        $rule->setDescription('Abattement entre époux/partenaires PACS');
        $rule->setTypeOfLink('epoux');
        $rule->setTypeOfAct(ActService::TYPE_ACT_DONATION);
        $rule->setAmountInCents($ABATEMENT_AMOUNTS['epoux']);
        $rule->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS);
        $rule->setMinBeneficiaryAge(0);
        $rule->setMaxDonorAge(200);
        $manager->persist($rule);

        // Règle 4: Frère/Sœur (Classique)
        $rule = new FiscalAbatementRule();
        $rule->setCode('ABATTEMENT_CLASSIQUE_FS');
        $rule->setDescription('Abattement entre Frère et Sœur');
        $rule->setTypeOfLink('frere_soeur');
        $rule->setTypeOfAct(ActService::TYPE_ACT_DONATION);
        $rule->setAmountInCents($ABATEMENT_AMOUNTS['frere_soeur']);
        $rule->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS);
        $rule->setMinBeneficiaryAge(0);
        $rule->setMaxDonorAge(200);
        $manager->persist($rule);

        // Règle 5: Tiers / Non parenté (Classique)
        $rule = new FiscalAbatementRule();
        $rule->setCode('ABATTEMENT_CLASSIQUE_TIERS');
        $rule->setDescription('Abattement Tiers / Non parenté');
        $rule->setTypeOfLink('tiers');
        $rule->setTypeOfAct(ActService::TYPE_ACT_DONATION);
        $rule->setAmountInCents($ABATEMENT_AMOUNTS['tiers']);
        $rule->setCycleOfYear(ActService::ABATEMENT_CYCLE_YEARS);
        $rule->setMinBeneficiaryAge(0);
        $rule->setMaxDonorAge(200);
        $manager->persist($rule);
        
        // Règle 6: Don Sarkozy (Art 790 G) - La règle spécifique
        $rule = new FiscalAbatementRule();
        $rule->setCode(ActService::CODE_SARKOZY_RULE); // <-- UTILISATION DE LA CONSTANTE DU SERVICE (ex: DON_SARKOZY_CUMULABLE)
        $rule->setDescription('Don familial de sommes d\'argent (Art. 790 G) - Cumulable');
        $rule->setTypeOfLink('descendants'); // Généraliser le lien pour cette règle spécifique
        $rule->setTypeOfAct(ActService::TYPE_ACT_DON_ARGENT_SEUL);
        $rule->setAmountInCents($ABATEMENT_AMOUNTS['don_sarkozy_cumulable']);
        $rule->setCycleOfYear(0); // Non cyclique / à vie
        $rule->setMinBeneficiaryAge(18); 
        $rule->setMaxDonorAge(80); 
        $manager->persist($rule);
    }
    
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