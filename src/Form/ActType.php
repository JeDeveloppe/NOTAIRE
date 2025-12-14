<?php

namespace App\Form;

use App\Entity\Act;
use App\Entity\Person;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use App\Entity\TypeAct; // Importation nécessaire pour la relation ManyToOne
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\IntegerType; // Utilisé pour 'value' (montant en euros)

class ActType extends AbstractType
{
    private $security;
    
    // Injection du service Security (pour filtrer les listes déroulantes de personnes)
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Récupérer l'utilisateur connecté pour filtrer les listes
        $user = $this->security->getUser();
        
        // Fonction de filtre pour n'afficher que les personnes appartenant à l'utilisateur
        $personQueryBuilder = function (EntityRepository $er) use ($user) {
            return $er->createQueryBuilder('p')
                ->where('p.owner = :user')
                ->orderBy('p.lastName', 'ASC')
                ->setParameter('user', $user);
        };

        $builder
            ->add('donor', EntityType::class, [
                'class' => Person::class,
                'label' => 'Donateur (Celui qui donne)',
                'query_builder' => $personQueryBuilder,
                'choice_label' => fn (Person $person) => $person->getFirstName() . ' ' . $person->getLastName(),
                'placeholder' => '--- Choisir le donateur ---',
                'required' => true,
            ])
            
            ->add('beneficiary', EntityType::class, [
                'class' => Person::class,
                'label' => 'Bénéficiaire (Celui qui reçoit)',
                'query_builder' => $personQueryBuilder,
                'choice_label' => fn (Person $person) => $person->getFirstName() . ' ' . $person->getLastName(),
                'placeholder' => '--- Choisir le bénéficiaire ---',
                'required' => true,
            ])
            
            // Le champ 'value' de l'entité est un INT (centimes), mais on le saisit en Euros
            // La conversion (*100) sera faite dans le contrôleur.
            ->add('value', IntegerType::class, [ 
                'label' => 'Montant Donné (€)',
                'help' => 'Saisissez le montant entier en euros (Ex: 100000 pour 100 000 €)',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan(0, message: 'Le montant doit être supérieur à zéro.'),
                ],
            ])
            
            // Relation ManyToOne vers l'entité TypeAct
            ->add('typeOfAct', EntityType::class, [
                'class' => TypeAct::class,
                'label' => 'Type Fiscal de l\'Acte',
                // Supposons que TypeAct a un champ 'name' ou 'label'
                'choice_label' => 'name', 
                'placeholder' => '--- Choisir le type d\'acte ---',
                'required' => true,
            ])
            
            ->add('dateOfAct', DateType::class, [
                'label' => 'Date de l\'Acte',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'html5' => true,
            ]);
            
            // Note : Le champ 'consumedAbatement' n'est pas inclus car il est calculé
            // et défini dans le contrôleur (ActController).
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Act::class,
            // Pour ne pas mapper le champ 'owner' qui est géré manuellement dans le contrôleur
            'allow_extra_fields' => true, 
        ]);
    }
}