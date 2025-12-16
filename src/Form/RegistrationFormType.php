<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\City;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType; // Reste l'EntityType standard

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
            ->add('email', TextType::class, [
                'label' => 'Adresse e-mail',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'exemple@domaine.fr',
                ],
            ])
            
            // 2. Champ d'auto-complétion pour la ville
            ->add('city', CityAutocompleteField::class)
            
            // 3. Sélection du Rôle (Non mappé)
            ->add('userRole', ChoiceType::class, [
                'mapped' => false,
                'label' => 'Vous êtes ?',
                'choices' => [
                    'Un particulier' => 'client',
                    'Notaire (Accès Professionnel)' => 'notaire',
                ],
                'expanded' => true, 
                'multiple' => false,
                'data' => 'client', 
                'row_attr' => ['class' => 'mt-3'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner votre statut.'),
                ],
            ])
            
            // 4. Mot de passe (Non mappé)
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe',
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
            
            // 5. Conditions d'utilisation (Non mappé)
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J\'accepte les conditions générales d\'utilisation',
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