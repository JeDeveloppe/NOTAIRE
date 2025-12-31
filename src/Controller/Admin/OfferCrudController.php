<?php

namespace App\Controller\Admin;

use App\Entity\Offer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use App\Form\OfferPriceType; // Assure-toi de créer ce FormType (voir étape 2)

class OfferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Offer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Offre')
            ->setEntityLabelInPlural('Offres')
            ->setSearchFields(['name', 'code']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        
        yield TextField::new('name', 'Nom de l\'offre')
            ->setHelp('Ex: Starter, Business, Premium');
            
        yield TextField::new('code', 'Code unique')
            ->setHelp('Identifiant technique interne (ex: OFFRE_STARTER)');

        yield TextField::new('badge', 'Badge (Optionnel)')
            ->setHelp('Texte mis en avant sur la carte (ex: Populaire)');

        yield TextEditorField::new('description', 'Description détaillée')
            ->hideOnIndex();

        yield IntegerField::new('baseNotariesCount', 'Nombre de secteurs inclus')
            ->setHelp('Nombre de codes postaux que le notaire peut réserver avec ce plan.');

        yield IntegerField::new('maxNotariesPerSector', 'Notaires max / secteur')
            ->setHelp('Limite de concurrence sur un même code postal.');

        yield BooleanField::new('isAddon', 'Est une option (Addon) ?')
            ->renderAsSwitch(true);

        yield BooleanField::new('isOnWebSite', 'Afficher sur le site ?')
            ->renderAsSwitch(true);

        // Gestion des prix via un sous-formulaire
        yield CollectionField::new('offerPrices', 'Historique des Tarifs')
            ->setEntryType(OfferPriceType::class)
            ->onlyOnForms();
            
        // Affichage du prix actuel sur la liste (Index)
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('currentPriceDisplay', 'Prix actuel HT')
                ->formatValue(function ($value, $entity) {
                    $price = $entity->getCurrentPrice();
                    return $price ? ($price->getAmountHt() . ' €') : 'Non défini';
                });
        }
    }
}