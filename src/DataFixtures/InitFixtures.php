<?php

namespace App\DataFixtures;

use App\Entity\TaxCatalog;
use App\Entity\TypeAct;
use App\Entity\User;
use App\Service\ActService; 
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface as FixturesBundleFixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\Common\DataFixtures\FixtureGroupInterface; // ⬅️ NÉCESSAIRE POUR LES GROUPES

class InitFixtures extends Fixture implements FixturesBundleFixtureGroupInterface 
{
    private array $relationshipCodes;
    private array $abatementAmountsInCents;
    private string $adminEmail;
    private string $adminPassword;

    public function __construct(
        private UserPasswordHasherInterface $hasher,
        ParameterBagInterface $parameterBag,
        string $adminEmail, 
        string $adminPassword,
        array $abatementAmountsInCents
    )
    {
        $this->relationshipCodes = $parameterBag->get('app.relationship_codes');
        $this->adminEmail = $adminEmail;
        $this->adminPassword = $adminPassword;
        $this->abatementAmountsInCents = $abatementAmountsInCents;
    }

    public static function getGroups(): array
    {
        return ['init'];
    }
    
    public function load(ObjectManager $manager): void
    {
        // ----------------------------------------------------
        // 1. UTILISATEURS DE BASE (Admin/Test)
        // ----------------------------------------------------
        $user = new User();
        $user->setEmail($this->adminEmail); 
        $user->setPassword($this->hasher->hashPassword($user, $this->adminPassword));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $manager->persist($user);
        
        // ----------------------------------------------------
        // 2. CATALOGUE FISCAL (TaxCatalog) - Les règles de base
        // ----------------------------------------------------
        
        $relationKeyMap = $this->createRelationKeyMap();

        foreach ($this->relationshipCodes as $name => $code) {
            
            if (isset($relationKeyMap[$code])) {
                $abatementKey = $relationKeyMap[$code];
                
                if (isset($this->abatementAmountsInCents[$abatementKey])) {
                    $taxRule = new TaxCatalog();
                    
                    $taxRule->setRelationshipBetweenThe2People($code);
                    $taxRule->setAbatementAmount($this->abatementAmountsInCents[$abatementKey]); 
                    $taxRule->setTaxRateLower(5); 
                    $taxRule->setTaxRateUpper(45); 
                    $manager->persist($taxRule);
                }
            }
        }

        // ----------------------------------------------------
        // 3. TYPES D'ACTE (TypeAct) - Les identifiants fiscaux et leurs règles
        // ----------------------------------------------------
        
        // Type 1 : CLASSIQUE Pleine Propriété
        $donationSimple = new TypeAct();
        $donationSimple->setName('Donation pleine propriété classique');
        $donationSimple->setCode(ActService::CODE_CLASSIQUE); 
        $donationSimple->setIsTaxReductible(true);
        // NOUVEAUX CHAMPS
        $donationSimple->setIsCyclical(true);
        $donationSimple->setFiscalRule('L\'abattement se reconstitue 15 ans après la date de chaque donation passée. Le montant de l\'abattement dépend du lien de parenté (parent/enfant, grand-parent/petit-enfant, etc.).');
        $donationSimple->setConditions('Aucune condition d\'âge spécifique. Applicable aux donations de biens ou de sommes d\'argent.');
        
        $manager->persist($donationSimple);
        
        // Type 2 : USUFRUIT (Nous le laissons simple, car il est peu utilisé dans la planification fiscale de la "reconstitution")
        $donationUsufruit = new TypeAct();
        $donationUsufruit->setName('Donation usufruit');
        $donationUsufruit->setCode(ActService::CODE_USUFRUIT ?? 'USUFRUIT'); 
        $donationUsufruit->setIsTaxReductible(true);
        // NOUVEAUX CHAMPS
        $donationUsufruit->setIsCyclical(true);
        $donationUsufruit->setFiscalRule('Règles similaires à la donation classique, mais la valeur taxable est déterminée par l\'âge de l\'usufruitier.');
        $donationUsufruit->setConditions('Nécessite une évaluation de la valeur de l\'usufruit selon le barème fiscal en vigueur.');
        
        $manager->persist($donationUsufruit);

        // Type 3 : SARKOZY (Don familial de sommes d'argent)
        $donationSarkozy = new TypeAct();
        $donationSarkozy->setName('Donation familiale d\'argent (Sarkozy)');
        $donationSarkozy->setCode(ActService::CODE_SARKOZY); 
        $donationSarkozy->setIsTaxReductible(true);
        // NOUVEAUX CHAMPS
        $donationSarkozy->setIsCyclical(false); // Non cyclique / Unique
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