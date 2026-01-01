<?php

namespace App\Form\Notary;

use App\Entity\City;
use App\Repository\CityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\ParentEntityAutocompleteType;

#[AsEntityAutocompleteField(alias: 'notary_postal_code')]
class PostalCodeAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => City::class,
            'placeholder' => 'Rechercher un code postal...',
            'choice_label' => 'postalCode',
            'multiple' => true,
            
            // Évite l'erreur "extra_options" avec EasyAdmin
            'extra_options' => [], 

            'tom_select_options' => [
                'hideSelected' => true,
            ],

            'query_builder' => function (CityRepository $cityRepository) {
                // 1. Sous-requête pour dédoublonner : un seul ID par Code Postal
                $subQueryIds = $cityRepository->createQueryBuilder('c2')
                    ->select('MIN(c2.id)')
                    ->groupBy('c2.postalCode');

                $qb = $cityRepository->createQueryBuilder('c');

                return $qb
                    // 2. On ne garde que les IDs uniques (un par CP)
                    ->where($qb->expr()->in('c.id', $subQueryIds->getDQL()))
                    
                    // 3. FILTRE DE QUOTA : Sous-requête SQL pour compter l'occupation
                    // On compare le nombre de sélections existantes avec la limite autorisée
                    ->andWhere('(
                        SELECT COUNT(sz.id) 
                        FROM App\Entity\SelectedZipCode sz 
                        WHERE sz.city = c
                    ) < c.maxNotariesCount')
                    
                    ->orderBy('c.postalCode', 'ASC');
            },

            'filter_query' => function (QueryBuilder $qb, string $query) {
                if (!$query) return;

                // Recherche par début de code postal ou nom de ville
                $qb->andWhere('c.postalCode LIKE :q OR c.name LIKE :q')
                   ->setParameter('q', $query.'%');
            },
        ]);

        // Autorise les options injectées par EasyAdmin
        $resolver->setDefined(['extra_options']);
    }

    public function getParent(): string
    {
        return ParentEntityAutocompleteType::class;
    }
}