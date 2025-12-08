<?php

namespace App\DataFixtures;

use App\Entity\Person;
use App\Entity\TaxCatalog;
use App\Entity\TypeAct;
use App\Entity\User;
use App\Entity\Hypothesis; // Assurez-vous d'avoir créé cette entité
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AppFixtures extends Fixture
{
    // Valeurs d'abattement en € (fixées ici pour le TaxCatalog, car elles ne sont pas dans services.yaml)
    private const ABATEMENT_MAP = [
        'R_PE' => 100000,   // Parent/Enfant
        'R_GPPE' => 31865,  // Grand-Parent/Petit-Enfant
        'R_FS' => 15932,    // Frère/Sœur
        'R_ON' => 7967,     // Oncle/Neveu
        'R_T' => 1594       // Tiers Éloigné
    ];
    
    private array $relationshipCodes;
    private string $adminEmail;
    private string $adminPassword;

    public function __construct(
        private UserPasswordHasherInterface $hasher,
        ParameterBagInterface $parameterBag,
        string $adminEmail, // Injecté depuis %env(ADMIN_EMAIL)%
        string $adminPassword // Injecté depuis %env(ADMIN_PASSWORD)%
    )
    {
        // Récupère le tableau des codes de relation depuis config/services.yaml
        $this->relationshipCodes = $parameterBag->get('app.relationship_codes');
        $this->adminEmail = $adminEmail;
        $this->adminPassword = $adminPassword;
    }

    public function load(ObjectManager $manager): void
    {
        // ----------------------------------------------------
        // 1. UTILISATEURS
        // ----------------------------------------------------

        // 1.b Utilisateur Administrateur (depuis .env.dev)
        $user = new User();
        $user->setEmail($this->adminEmail); 
        $user->setPassword($this->hasher->hashPassword($user, $this->adminPassword));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $manager->persist($user);


        // ----------------------------------------------------
        // 2. CATALOGUE FISCAL (TaxCatalog) - Synchronisé avec services.yaml
        // ----------------------------------------------------
        foreach ($this->relationshipCodes as $name => $code) {
            $taxRule = new TaxCatalog();
            
            // Le code (ex: 'R_PE') est tiré de la configuration YAML
            $taxRule->setRelationshipBetweenThe2People($code);
            
            // L'abattement est tiré de notre map interne via le code YAML
            $taxRule->setAbatementAmount(self::ABATEMENT_MAP[$code] * 100); // En centimes
            
            $taxRule->setTaxRateLower(5); 
            $taxRule->setTaxRateUpper(45); 
            $manager->persist($taxRule);
        }

        // ----------------------------------------------------
        // 3. TYPES D'ACTE (TypeAct)
        // ----------------------------------------------------
        $donationSimple = new TypeAct();
        $donationSimple->setName('Donation Pleine Propriété');
        $donationSimple->setIsTaxReductible(true);
        $manager->persist($donationSimple);
        
        $donationUsufruit = new TypeAct();
        $donationUsufruit->setName('Donation Usufruit');
        $donationUsufruit->setIsTaxReductible(true);
        $manager->persist($donationUsufruit);

        // ----------------------------------------------------
        // 4. ARBRE GÉNÉALOGIQUE (Person) - Sous l'utilisateur standard ($user)
        // ----------------------------------------------------

        // G1 : Grands-Parents
        $grandpa = $this->createPerson('Jean', 'Wetta', '1940-05-15', $user, $manager);
        $grandma = $this->createPerson('Marie', 'Wetta', '1942-08-20', $user, $manager);
        
        // G2 : Enfants (Paul est le Parent direct, Sophie est la Tante)
        $parent = $this->createPerson('Paul', 'Wetta', '1970-01-01', $user, $manager);
        $aunt = $this->createPerson('Sophie', 'Wetta', '1975-11-11', $user, $manager);
        
        // Parenté G1 -> G2 (Frère/Sœur créé implicitement)
        $parent->addParent($grandpa)->addParent($grandma);
        $aunt->addParent($grandpa)->addParent($grandma);

        // G3 : Le Petit-Enfant et la Nièce/Neveu (Alice)
        $me = $this->createPerson('Alice', 'Wetta', '2000-03-25', $user, $manager);
        $me->addParent($parent); // Alice est enfant de Paul (et Petit-Enfant de Jean/Marie)
        
        // Cousin pour le test Oncle/Nièce
        $cousin = $this->createPerson('Julien', 'Martin', '2005-07-07', $user, $manager);
        $cousin->addParent($aunt); // Julien est enfant de Sophie (Nièce/Neveu de Paul)
        
        // Tiers Éloigné
        $stranger = $this->createPerson('Inconnu', 'Tiers', '1980-01-01', $user, $manager);

        // Personne décédée (pour test de validation)
        $deceasedDonor = $this->createPerson('Defunt', 'X', '1950-01-01', $user, $manager, '2020-01-01');
        
        // ----------------------------------------------------
        // 5. HYPOTHÈSES DE TEST (Hypothesis)
        // ----------------------------------------------------
        
        // H1 : Parent/Enfant (R_PE)
        $this->createHypothesis($parent, $me, 100000, $donationSimple, $manager); // Paul -> Alice
        $this->createHypothesis($me, $parent, 10000, $donationSimple, $manager); // Alice -> Paul (Symétrique)

        // H2 : Grand-Parent/Petit-Enfant (R_GPPE)
        $this->createHypothesis($grandpa, $me, 50000, $donationSimple, $manager); // Jean -> Alice

        // H3 : Frère/Sœur (R_FS)
        $this->createHypothesis($parent, $aunt, 15000, $donationSimple, $manager); // Paul -> Sophie

        // H4 : Oncle/Nièce (R_ON)
        $this->createHypothesis($aunt, $me, 10000, $donationSimple, $manager); // Sophie (Tante) -> Alice (Nièce)

        // H5 : Tiers Éloigné (R_T)
        $this->createHypothesis($me, $stranger, 5000, $donationUsufruit, $manager);

        // H6 : Test de Validation (Donateur décédé - Doit échouer la validation)
        $this->createHypothesis($deceasedDonor, $me, 1000, $donationSimple, $manager);
        
        $manager->flush();
    }
    
    // ----------------------------------------------------
    // --- METHODES UTILITAIRES ---
    // ----------------------------------------------------
    
    private function createPerson(string $firstName, string $lastName, string $dob, User $owner, ObjectManager $manager, ?string $dod = null): Person
    {
        $person = new Person();
        $person->setFirstName($firstName);
        $person->setLastName($lastName);
        $person->setDateOfBirth(new \DateTimeImmutable($dob));
        if ($dod) {
             $person->setDateOfDeath(new \DateTimeImmutable($dod));
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
        $hypothesis->setDateOfSimulation(new \DateTimeImmutable());
        $manager->persist($hypothesis);
        
        return $hypothesis;
    }
}