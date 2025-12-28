<?php

namespace App\Form;

use App\Entity\Person;
use App\Repository\PersonRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PersonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 1. Récupération des données contextuelles
        $user = $options['user'];
        $person = $builder->getData();
        
        // On identifie l'ID de la personne actuelle (null si c'est une création)
        $currentId = ($person && $person->getId()) ? $person->getId() : null;

        // 2. Champs de base (Toujours présents)
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control rounded-3', 
                    'placeholder' => 'ex: Jean'
                ]
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom de famille',
                'attr' => [
                    'class' => 'form-control rounded-3 text-uppercase', 
                    'placeholder' => 'ex: DUPONT'
                ]
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre / Civilité',
                'choices' => [
                    'Masculin' => 'M',
                    'Féminin' => 'F',
                ],
                'expanded' => true,
                'multiple' => false,
                'label_attr' => ['class' => 'form-label fw-bold'],
                'choice_attr' => function () {
                    return ['class' => 'form-check-inline'];
                },
            ])
            ->add('birthdate', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control rounded-3']
            ]);

        // 3. Logique de filtrage pour les relations (Parents / Enfants)
        if (!$options['is_first_person']) {
            
            /** * Fonction de filtrage réutilisable pour exclure l'entité actuelle
             */
            $queryFilter = function (PersonRepository $er) use ($user, $currentId) {
                $qb = $er->createQueryBuilder('p')
                    ->where('p.owner = :user')
                    ->setParameter('user', $user);

                // Si on est en édition, on exclut la personne en cours pour éviter qu'elle soit son propre parent/enfant
                if ($currentId) {
                    $qb->andWhere('p.id != :currentId')
                       ->setParameter('currentId', $currentId);
                }

                return $qb->orderBy('p.firstname', 'ASC');
            };

            $builder
                ->add('deathDate', DateType::class, [
                    'label' => 'Date de décès (si applicable)',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => false,
                    'attr' => ['class' => 'form-control rounded-3']
                ])
                ->add('parents', EntityType::class, [
                    'class' => Person::class,
                    'label' => 'Sélectionner le(s) parent(s)',
                    'multiple' => true,
                    'expanded' => true, // Liste de cases à cocher pour une meilleure UX
                    'required' => false,
                    'by_reference' => false, // INDISPENSABLE
                    'query_builder' => $queryFilter,
                    'choice_label' => fn(Person $p) => $p->getFirstname() . ' ' . $p->getLastname(),
                    'attr' => ['class' => 'form-check-group border p-3 rounded-4 bg-light-subtle']
                ])
                ->add('children', EntityType::class, [
                    'class' => Person::class,
                    'label' => 'Ses Enfants',
                    'multiple' => true,
                    'expanded' => true,
                    'required' => false,
                    'by_reference' => false, // INDISPENSABLE
                    'query_builder' => $queryFilter,
                    'choice_label' => fn(Person $p) => $p->getFirstname() . ' ' . $p->getLastname(),
                    'attr' => ['class' => 'form-check-group border p-3 rounded-4 bg-light-subtle']
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Person::class,
            'is_first_person' => false,
            'user' => null,
        ]);

        // L'utilisateur est obligatoire pour filtrer les données par propriétaire (sécurité)
        $resolver->setRequired('user');
    }
}