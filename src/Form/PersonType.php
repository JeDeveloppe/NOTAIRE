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
        // On récupère l'utilisateur connecté passé depuis le contrôleur
        $user = $options['user'];

        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'ex: Jean']
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom de famille',
                'attr' => ['class' => 'form-control', 'placeholder' => 'ex: DUPONT']
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre / Civilité',
                'choices' => [
                    'Masculin' => 'M',
                    'Féminin' => 'F',
                ],
                'expanded' => true,
                'multiple' => false,
                // Cette option ajoute la classe Bootstrap pour l'alignement horizontal
                'label_attr' => ['class' => 'form-label'],
                'choice_attr' => function () {
                    return ['class' => 'form-check-inline'];
                },
            ])
            ->add('birthdate', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control']
            ]);

        // Si ce n'est pas la première personne, on affiche les options avancées
        if (!$options['is_first_person']) {
            $builder
                ->add('deathDate', DateType::class, [
                    'label' => 'Date de décès (si nécessaire)',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => false,
                    'attr' => ['class' => 'form-control']
                ])
                ->add('parents', EntityType::class, [
                    'class' => Person::class,
                    'label' => 'Sélectionner le(s) parent(s)',
                    'multiple' => true,
                    'expanded' => true,
                    'required' => false,
                    'placeholder' => 'Choisir dans la liste...',
                    // Filtrage crucial : on ne montre que les personnes de cet OWNER
                    'query_builder' => function (PersonRepository $er) use ($user) {
                        return $er->createQueryBuilder('p')
                            ->where('p.owner = :user')
                            ->setParameter('user', $user)
                            ->orderBy('p.firstname', 'ASC');
                    },
                    'choice_label' => function (Person $person) {
                        return $person->getFirstname() . ' ' . $person->getLastname();
                    },
                    'attr' => [
                        'class' => 'form-select',
                        'size' => '5' // Permet de voir plusieurs choix d'un coup
                    ]
                ])
                ->add('children', EntityType::class, [
                    'class' => Person::class,
                    'label' => 'Ses Enfants',
                    'multiple' => true,
                    'expanded' => true,
                    'required' => false,
                    'query_builder' => fn(PersonRepository $er) => $er->createQueryBuilder('p')
                        ->where('p.owner = :user')
                        ->setParameter('user', $user)
                        ->orderBy('p.firstname', 'ASC'),
                    'choice_label' => fn(Person $p) => $p->getFirstname() . ' ' . $p->getLastname(),
                    'attr' => ['class' => 'form-select']
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Person::class,
            'is_first_person' => false,
            'user' => null, // Nécessaire pour recevoir l'objet User du contrôleur
        ]);

        // On force la présence de l'utilisateur pour le filtrage
        $resolver->setRequired('user');
    }
}
