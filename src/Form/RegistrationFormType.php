<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType; // NOUVEL IMPORT
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            
            ->add('locationInput', TextType::class, [
                'mapped' => false,
                'label' => 'Ville ou Code Postal',
                'attr' => [
                    'placeholder' => 'Ex: 14123 ou Bourguébus',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner votre ville ou code postal.'), 
                    new Length(
                        min: 4, 
                        minMessage: 'La saisie doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            
            // ⭐️ Remplacement par un ChoiceType pour la sélection du rôle ⭐️
            ->add('userRole', ChoiceType::class, [
                'mapped' => false, // NON mappé à l'entité User
                'label' => 'Vous êtes ?',
                'choices' => [
                    'Un particulier' => 'client',
                    'Notaire (Accès Professionnel)' => 'notaire',
                ],
                // Affichage en boutons radio (plus engageant qu'un simple select)
                'expanded' => true, 
                'multiple' => false,
                'data' => 'client', // Valeur par défaut pour minimiser les erreurs
                'row_attr' => ['class' => 'mt-3'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner votre statut.'),
                ],
            ])
            
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un mot de passe'),
                    new Length(
                        min: 6, 
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter nos conditions.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}