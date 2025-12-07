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

    public function __construct(Security $security)
    {
        // Nous n'injectons plus PersonRepository directement ici
        // car il est déjà passé au query_builder dans buildForm.
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        // ⭐ Récupérer l'option passée par le contrôleur
        $isInitialCreation = $options['is_initial_creation'] ?? false;

        // Query Builder optimisé pour un tri UX
        $qb = function (PersonRepository $repo) use ($currentUser) {
            if (!$currentUser) {
                // Empêche l'affichage si aucun utilisateur n'est connecté
                return $repo->createQueryBuilder('p')->where('1 = 0');
            }
            return $repo->createQueryBuilder('p')
                ->where('p.owner = :owner')
                ->setParameter('owner', $currentUser)
                ->orderBy('p.lastName', 'ASC')
                ->addOrderBy('p.firstName', 'ASC')
                ->addOrderBy('p.dateOfBirth', 'ASC');
        };

        // Fonction de rappel pour afficher Nom et Date de Naissance dans les choix (pour les relations)
        $choiceLabelCallback = function (Person $person) {
            $dob = $person->getDateOfBirth() ? $person->getDateOfBirth()->format('d/m/Y') : 'Inconnue';
            return $person->getLastName() . ' ' . $person->getFirstName() . ' (Né(e) le ' . $dob . ')';
        };

        $builder
            // --- INFORMATIONS DE BASE (TOUJOURS AFFICHÉES) ---
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => true, // On rend les champs obligatoires pour la personne initiale
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => true,
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'html5' => true,
                'required' => false,
            ]);
            
        // ⭐ CONDITION : Ajouter les relations UNIQUEMENT si ce n'est PAS la création initiale
        if (!$isInitialCreation) {
            
            // Option 1 : Est enfant de (relations 'parents')
            $builder->add('parents', EntityType::class, [
                'class' => Person::class,
                'choice_label' => $choiceLabelCallback,
                'label' => 'Parents', // Ajout d'une étiquette pour le mode non-initial
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => $qb,
            ]);
            
            // Option 2 : Est parent de (relations 'children')
            $builder->add('children', EntityType::class, [
                'class' => Person::class,
                'choice_label' => $choiceLabelCallback,
                'label' => 'Enfants', // Ajout d'une étiquette pour le mode non-initial
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => $qb,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Person::class,
            // ⭐ Définir la nouvelle option avec une valeur par défaut de false
            'is_initial_creation' => false, 
        ]);
    }
}