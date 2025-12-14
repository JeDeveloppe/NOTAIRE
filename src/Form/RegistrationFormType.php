<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank; // Assurez-vous d'avoir bien importé la classe

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
                    // ⭐️ CORRECTION NotBlank : Utiliser l'argument nommé 'message'
                    new NotBlank(message: 'Veuillez renseigner votre ville ou code postal.'), 
                    // ⭐️ CORRECTION Length : Utiliser les arguments nommés 'min' et 'minMessage'
                    new Length(
                        min: 4, 
                        minMessage: 'La saisie doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    // ⭐️ CORRECTION NotBlank
                    new NotBlank(message: 'Veuillez saisir un mot de passe'),
                    // ⭐️ CORRECTION Length
                    new Length(
                        min: 6, 
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    // ⭐️ CORRECTION IsTrue
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