<?php

namespace App\Controller\Admin;

use App\Entity\Person;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\Date;

class PersonCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Person::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('dateOfBirth', 'Date de naissance')->setFormat('dd/MM/yyyy')->setRequired(true),
            TextField::new('firstName', 'Prénom'),
            TextField::new('lastName', 'Nom de famille'),
            DateTimeField::new('dateOfDeath', 'Date de décès')->setFormat('dd/MM/yyyy')->setRequired(false),
            AssociationField::new('owner', 'Utilisateur associé'),
        ];
    }
}
