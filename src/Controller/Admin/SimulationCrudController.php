<?php

namespace App\Controller\Admin;

use App\Entity\Simulation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SimulationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Simulation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Simulation')
            ->setEntityLabelInPlural('Simulations')
            ->setSearchFields(['reference', 'user.email', 'user.lastname'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        // 1. Les champs qui apparaissent TOUJOURS sur l'index (la liste)
        // On les place avant les onglets ou on utilise hideOnDetail/hideOnForm
        yield IdField::new('id')->hideOnForm()->hideOnIndex()->hideOnDetail();
        
        // 2. Premier Onglet : Informations générales
        yield FormField::addTab('Dossier Client');
        
        yield TextField::new('reference', 'Référence')
            ->setColumns(6)
            ->setFormTypeOptions(['disabled' => true]);

        yield AssociationField::new('status', 'Statut actuel')
            ->setColumns(6)
            ->formatValue(function ($value, $entity) {
                return sprintf('<span class="badge" style="background:%s">%s</span>', 
                    $entity->getStatus()?->getColor() ?? '#ccc', 
                    $entity->getStatus()?->getLabel() ?? 'N/A'
                );
            });

        yield AssociationField::new('user', 'Propriétaire (Client)')
            ->setColumns(6);

        yield DateTimeField::new('createdAt', 'Date de création')
            ->onlyOnDetail()
            ->setColumns(6);

        // 3. Deuxième Onglet : Notaire
        yield FormField::addTab('Notaire actuel sur le dossier');
        
        yield AssociationField::new('reservedBy', 'Notaire en charge')
            ->setColumns(6);
            
        yield DateTimeField::new('reservedAt', 'Date de réservation')
            ->setColumns(3);
            
        yield DateTimeField::new('availableAt', 'Dernière libération')
            ->setColumns(3);

        // 4. Troisième Onglet : Historique
        yield FormField::addTab('Historique des étapes');
        
        yield CollectionField::new('simulationSteps', 'Journal des événements')
            ->onlyOnDetail()
            ->setTemplatePath('admin/fields/simulation_history.html.twig');
    }
}