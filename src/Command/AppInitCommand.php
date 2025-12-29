<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Person;
use App\Entity\Donation;
use App\Entity\DonationRule;
use App\Entity\Relationship;
use Doctrine\ORM\EntityManagerInterface;
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
        private array $relationships,
        private array $donationRules,
        private string $adminEmail,
        private string $adminPassword
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

        // 2. Import des Relationships
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

        // 3. Import des DonationRules (MODIFIÉ ICI)
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

            // INDISPENSABLE : On récupère le tax_system du YAML
            $rule->setTaxSystem($data['tax_system'] ?? 'tiers');

            $rule->setRelationship($createdRels[$data['relationship_code']] ?? null);
            $this->em->persist($rule);
            $io->text("-> Ajout de la règle : " . $data['label'] . " (Système : " . ($data['tax_system'] ?? 'tiers') . ")");
        }

        // 4. Création Admin
        $io->section('Création de l\'administrateur');
        $admin = new User();
        $admin->setEmail($this->adminEmail);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, $this->adminPassword));
        $this->em->persist($admin);
        $io->text("-> Admin créé : " . $this->adminEmail);

        // 5. Création d'une famille de test
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

        $tante = new Person();
        $tante->setFirstname('Marie')->setLastname('Dubois')->setGender('F')
            ->setBirthdate(new \DateTimeImmutable('1975-03-14'))->setOwner($admin);
        $tante->addParent($gp);
        $this->em->persist($tante);

        $enfant = new Person();
        $enfant->setFirstname('Marc')->setLastname('Dubois')->setGender('M')
            ->setBirthdate(new \DateTimeImmutable('2002-01-10'))->setOwner($admin);
        $enfant->addParent($pere);
        $this->em->persist($enfant);

        $this->em->flush();

        // 6. Simulation d'une donation passée (Rappel fiscal)
        $io->section('Simulation d\'une donation de 80k€');

        $don = new Donation();
        $don->setDonor($pere); // Jean donne...
        $don->setBeneficiary($enfant); // ...à Marc
        $don->setAmount(80000);
        // Date : il y a 2 ans (donc dans le délai des 15 ans)
        $don->setCreatedAt(new \DateTimeImmutable('-2 years'));
        $don->setDonateAt(new \DateTimeImmutable('-2 years'));
        // TRÈS IMPORTANT : Le type doit correspondre au tax_system de votre règle YAML
        // Si votre règle "Enfant" utilise 'progressif_direct', mettez 'progressif_direct' ici.
        $don->setType('progressif_direct');
        $don->setTaxPaid(0);

        $this->em->persist($don);
        $io->text("-> Donation de 80 000 € créée (Jean -> Marc)");

        $this->em->flush();

        $io->success('Base de données initialisée avec succès !');

        return Command::SUCCESS;
    }
}
