<?php

namespace App\Form;

use App\Entity\Person;
use App\Entity\User;
use App\Repository\PersonRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PersonType extends AbstractType
{
    private Security $security;
    private PersonRepository $personRepository;

    public function __construct(Security $security, PersonRepository $personRepository)
    {
        $this->security = $security;
        $this->personRepository = $personRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        // Query Builder optimisé pour un tri UX : Nom, Prénom, Date de Naissance
        $qb = function (PersonRepository $repo) use ($currentUser) {
            if (!$currentUser) {
                return $repo->createQueryBuilder('p')->where('1 = 0');
            }
            return $repo->createQueryBuilder('p')
                ->where('p.owner = :owner')
                ->setParameter('owner', $currentUser)
                ->orderBy('p.lastName', 'ASC')
                ->addOrderBy('p.firstName', 'ASC')
                ->addOrderBy('p.dateOfBirth', 'ASC');
        };

        // Fonction de rappel pour afficher Nom et Date de Naissance dans les choix
        $choiceLabelCallback = function (Person $person) {
            // Assure un format clair JJ/MM/AAAA
            $dob = $person->getDateOfBirth() ? $person->getDateOfBirth()->format('d/m/Y') : 'Inconnue';
            // Affiche le Nom, le Prénom (si vous le souhaitez, j'ai conservé le Nom seul par souci de lisibilité dans la checkbox)
            return $person->getLastName() . ' ' . $person->getFirstName() . ' (Né(e) le ' . $dob . ')';
        };

        $builder
            // --- INFORMATIONS DE BASE ---
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'html5' => true,
            ])
            
            // --- RELATIONS DE PARENTÉ ---
            
            // Option 1 : Est enfant de (relations 'parents')
            ->add('parents', EntityType::class, [
                'class' => Person::class,
                'choice_label' => $choiceLabelCallback,
                'label' => false, // Étiquette gérée par le template Twig
                'multiple' => true,
                'expanded' => true, // Affichage en cases à cocher
                'required' => false,
                'query_builder' => $qb,
            ])
            
            // Option 2 : Est parent de (relations 'children')
            ->add('children', EntityType::class, [
                'class' => Person::class,
                'choice_label' => $choiceLabelCallback,
                'label' => false, // Étiquette gérée par le template Twig
                'multiple' => true,
                'expanded' => true, // Affichage en cases à cocher
                'required' => false,
                'query_builder' => $qb,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Person::class,
        ]);
    }
}