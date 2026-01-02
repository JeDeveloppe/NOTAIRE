<?php

namespace App\Controller\Admin;

use App\Entity\SimulationStep;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SimulationStepCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SimulationStep::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Étape de suivi')
            ->setEntityLabelInPlural('Historique des dossiers')
            // Tri par défaut : le plus récent en premier
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Comme c'est un historique, on désactive souvent la création et l'édition manuelle
        // pour que seuls tes services (comme reserveSimulation) puissent générer des étapes.
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::EDIT, Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        
        yield DateTimeField::new('createdAt', 'Date/Heure')
            ->setFormat('dd/MM/Y HH:mm');

        yield AssociationField::new('simulation', 'Dossier concerné');

        yield AssociationField::new('status', 'Statut appliqué')
            ->formatValue(function ($value, $entity) {
                $color = $entity->getStatus()?->getColor() ?? 'secondary';
                $label = $entity->getStatus()?->getLabel();
                return sprintf('<span class="badge bg-%s">%s</span>', $color, $label);
            })
            ->renderAsHtml();

        yield IntegerField::new('status.points', 'Points générés')
            ->formatValue(function ($value) {
                $class = $value >= 0 ? 'text-success' : 'text-danger';
                return sprintf('<span class="%s fw-bold">%s %d</span>', $class, $value >= 0 ? '+' : '', $value);
            });

        // Affiche l'auteur (soit un admin, soit le notaire directement)
        yield AssociationField::new('changedByUser', 'Modifié par (Admin)');
        yield AssociationField::new('changeByNotary', 'Action par le Notaire');
    }
}