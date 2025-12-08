<?php

namespace App\Controller\Admin;

use App\Entity\Act;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ActCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Act::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('dateOfAct', 'Date de l\'acte'),
            MoneyField::new('value', 'Valeur de l\'acte')->setStoredAsCents(true)->setCurrency('EUR'),
            MoneyField::new('consumedAbatement', 'Abattement consommé')->setStoredAsCents(true)->setCurrency('EUR'),
            AssociationField::new('donor', 'Donateur'),
            AssociationField::new('beneficiary', 'Bénéficiaire'),
            AssociationField::new('typeOfAct', 'Type d\'acte'),
        ];
    }
}
