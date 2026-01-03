<?php

namespace App\Form;

use App\Entity\City;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => [
                    'class' => 'form-control border-0 bg-light rounded-4 px-4',
                    'placeholder' => 'nom@exemple.com'
                ],
                'label' => 'Adresse Email',
                'required' => true
            ])
            ->add('city', CityAutocompleteField::class)
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control border-0 bg-light rounded-4 px-4',
                    'placeholder' => 'Mot de passe'
                ],
                'label' => 'Mot de passe',
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer un mot de passe'),
                    new Length(
                        min: 6,
                        minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères',
                        max: 4096,
                    ),
                ],
            ])
            ->add('firstname', TextType::class, [
                'attr' => [
                    'class' => 'form-control border-0 bg-light rounded-4 px-4',
                    'placeholder' => 'Prénom'
                ],
                'label' => 'Prénom',
                'required' => true
            ])
            ->add('lastname', TextType::class, [
                'attr' => [
                    'class' => 'form-control border-0 bg-light rounded-4 px-4',
                    'placeholder' => 'Nom'
                ],
                'label' => 'Nom',
                'required' => true
            ])
            ->add('phone', TelType::class, [
                'attr' => [
                    'class' => 'form-control border-0 bg-light rounded-4 px-4',
                    'placeholder' => '06.00.00.00.00'
                ],
                'constraints' => [
                    new Regex(
                        pattern: '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
                        message:'Le numéro de téléphone n\'est pas valide.'
                    )
                ],
                'label' => 'Téléphone',
                'required' => true
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'required' => true,
                'label' => false, // Désactive le label automatique pour éviter le doublon
                'attr' => ['class' => 'form-check-input me-2'],
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter nos conditions pour continuer.'),
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
