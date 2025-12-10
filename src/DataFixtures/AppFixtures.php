<?php

namespace App\DataFixtures;

use App\Entity\Act; 
use App\Entity\Person;
use App\Entity\TaxCatalog;
use App\Entity\TypeAct;
use App\Entity\User;
use App\Entity\Hypothesis; 
use App\Service\ActService; // ⬅️ Utilisation de TypeActService pour les constantes fiscales
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use DateTimeImmutable;

class AppFixtures extends Fixture
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

    public function load(ObjectManager $manager): void
    {
        // ----------------------------------------------------
        // 1. UTILISATEURS
        // ----------------------------------------------------
        $user = new User();
        $user->setEmail($this->adminEmail); 
        $user->setPassword($this->hasher->hashPassword($user, $this->adminPassword));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $manager->persist($user);
        $this->addReference('admin-user', $user);
        
        // ----------------------------------------------------
        // 2. CATALOGUE FISCAL (TaxCatalog)
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
        // 3. TYPES D'ACTE (TypeAct) - CORRECTION DE L'UNICITÉ
        // ----------------------------------------------------
        
        // Type 1 : CLASSIQUE Pleine Propriété
        $donationSimple = new TypeAct();
        $donationSimple->setName('Donation Pleine Propriété Classique');
        $donationSimple->setCode(ActService::CODE_CLASSIQUE); 
        $donationSimple->setIsTaxReductible(true);
        $manager->persist($donationSimple);
        $this->addReference('type-classique', $donationSimple);
        
        // Type 2 : USUFRUIT (Nouveau code unique pour éviter l'erreur)
        $donationUsufruit = new TypeAct();
        $donationUsufruit->setName('Donation Usufruit');
        $donationUsufruit->setCode(ActService::CODE_USUFRUIT ?? 'USUFRUIT'); // Utiliser la constante si définie, sinon une chaîne
        $donationUsufruit->setIsTaxReductible(true);
        $manager->persist($donationUsufruit);
        $this->addReference('type-usufruit', $donationUsufruit);

        // Type 3 : SARKOZY
        $donationSarkozy = new TypeAct();
        $donationSarkozy->setName('Donation Familiale d\'Argent (Sarkozy)');
        $donationSarkozy->setCode(ActService::CODE_SARKOZY); 
        $donationSarkozy->setIsTaxReductible(true);
        $manager->persist($donationSarkozy);
        $this->addReference('type-sarkozy', $donationSarkozy);
        
        // ----------------------------------------------------
        // 4. ARBRE GÉNÉALOGIQUE (Person)
        // ----------------------------------------------------
        
        // G1 : Grands-Parents
        $grandpa = $this->createPerson('Jean', 'Wetta', '1940-05-15', $user, $manager);
        $grandma = $this->createPerson('Marie', 'Wetta', '1942-08-20', $user, $manager);
        // G2 : Enfants
        $parent = $this->createPerson('Paul', 'Wetta', '1970-01-01', $user, $manager);
        $aunt = $this->createPerson('Sophie', 'Wetta', '1975-11-11', $user, $manager);
        // Parenté G1 -> G2
        $parent->addParent($grandpa)->addParent($grandma);
        $aunt->addParent($grandpa)->addParent($grandma);
        // G3 : Le Petit-Enfant (Alice)
        $me = $this->createPerson('Alice', 'Wetta', '2000-03-25', $user, $manager);
        $me->addParent($parent); 
        // Tiers Éloigné
        $stranger = $this->createPerson('Inconnu', 'Tiers', '1980-01-01', $user, $manager);
        
        $this->addReference('person-parent', $parent); 
        $this->addReference('person-me', $me); 

        // ----------------------------------------------------
        // 5. HYPOTHÈSES DE TEST (Hypothesis)
        // ----------------------------------------------------
        
        // H1 : Parent/Enfant (R_PE) - ACTE CLASSIQUE
        $this->createHypothesis($parent, $me, 100000, $donationSimple, $manager);

        // H6 : Test Don Sarkozy 
        $this->createHypothesis($grandpa, $me, 31865, $donationSarkozy, $manager); 

        // ----------------------------------------------------
        // 6. ACTES DÉCLARÉS (Act) - DONATIONS PASSÉES RÉELLES
        // ----------------------------------------------------
        
        // A1 : Acte classique (Parent -> Enfant) il y a 5 ans
        // Montant : 40 000 €. Consomme 40 000 € de l'abattement 100k€.
        $this->createAct(
            $parent, 
            $me, 
            40000, 
            $donationSimple, 
            new DateTimeImmutable('2020-01-01'), 
            $user, 
            $manager
        );

        // A2 : Acte classique (Parent -> Enfant) il y a 16 ans (Prescrit)
        // Montant : 20 000 €. Ne doit plus impacter la simulation.
        $this->createAct(
            $parent, 
            $me, 
            20000, 
            $donationSimple, 
            new DateTimeImmutable('2009-01-01'), 
            $user, 
            $manager
        );

        // A3 : Don Sarkozy (Grand-père -> Petit-enfant) il y a 1 an
        // Montant : 31 865 €. Ne consomme PAS l'abattement de 100k€ (consumedAbatement = 0).
        $this->createAct(
            $grandpa, 
            $me, 
            31865, 
            $donationSarkozy, 
            new DateTimeImmutable('2024-10-01'), 
            $user, 
            $manager
        );

        $manager->flush();
    }
    
    // ----------------------------------------------------
    // MÉTHODES UTILITAIRES
    // ----------------------------------------------------

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
    
    private function createPerson(string $firstName, string $lastName, string $dob, User $owner, ObjectManager $manager, ?string $dod = null): Person
    {
        $person = new Person();
        $person->setFirstName($firstName);
        $person->setLastName($lastName);
        $person->setDateOfBirth(new DateTimeImmutable($dob));
        if ($dod) {
             $person->setDateOfDeath(new DateTimeImmutable($dod));
        }
        $person->setOwner($owner);
        $manager->persist($person);
        return $person;
    }
    
    private function createHypothesis(Person $donor, Person $beneficiary, int $value, TypeAct $typeAct, ObjectManager $manager): Hypothesis
    {
        $hypothesis = new Hypothesis();
        $hypothesis->setDonor($donor);
        $hypothesis->setBeneficiary($beneficiary);
        $hypothesis->setSimulatedValue($value * 100); // En centimes
        $hypothesis->setTypeOfActSimulated($typeAct);
        $hypothesis->setDateOfSimulation(new DateTimeImmutable());
        $manager->persist($hypothesis);
        
        return $hypothesis;
    }
    
    /**
     * Crée un acte de donation passé (Act) pour l'historique de l'utilisateur.
     */
    private function createAct(
        Person $donor, 
        Person $beneficiary, 
        int $value, // Valeur en euros
        TypeAct $typeAct, 
        DateTimeImmutable $dateOfAct, 
        User $owner, 
        ObjectManager $manager
    ): Act
    {
        $act = new Act();
        $act->setDonor($donor);
        $act->setBeneficiary($beneficiary);
        $act->setTypeOfAct($typeAct);
        $act->setDateOfAct($dateOfAct);
        $act->setValue($value * 100); // Stockage en centimes
        $act->setOwner($owner);

        // LOGIQUE D'ABATTEMENT CONSOMMÉ
        // Utilise le code stocké dans l'entité TypeAct
        if ($typeAct->getCode() === ActService::CODE_SARKOZY) {
            $consumed = 0;
        } else {
            // Tous les autres codes (CLASSIQUE, USUFRUIT, etc.) consomment l'abattement
            $consumed = $value * 100; 
        }

        $act->setConsumedAbatement($consumed);
        $manager->persist($act);
        
        return $act;
    }
}