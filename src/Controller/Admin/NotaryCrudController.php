<?php

namespace App\Controller\Admin;

use App\Entity\Notary;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class NotaryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Notary::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Notaire')
            ->setEntityLabelInPlural('Notaires')
            ->setSearchFields(['name', 'siret', 'address'])
            ->setDefaultSort(['isConfirmed' => 'ASC', 'confirmedAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Création de l'action personnalisée "Valider"
        $validateNotary = Action::new('validateNotary', 'Valider l\'étude', 'fa fa-check-circle')
            ->linkToCrudAction('validateNotary')
            ->displayIf(fn(Notary $notary) => !$notary->isConfirmed())
            ->setCssClass('btn btn-outline-success');

        return $actions
            ->add(Crud::PAGE_INDEX, $validateNotary)
            ->add(Crud::PAGE_DETAIL, $validateNotary)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) => $action->setIcon('fa fa-edit'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn(Action $action) => $action->setIcon('fa fa-trash'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom de l\'étude');
        yield TextField::new('siret', 'SIRET')->hideOnIndex();

        // Accès à l'email de l'utilisateur lié
        yield TextField::new('user.email', 'Email utilisateur')->onlyOnIndex();

        yield TelephoneField::new('phone', 'Téléphone');
        yield TextField::new('address', 'Adresse')->hideOnIndex();
        
        // La ville s'affichera correctement si vous avez le __toString() dans l'entité City
        yield AssociationField::new('city', 'Ville')
            ->autocomplete();

        yield UrlField::new('website', 'Site Web')->hideOnIndex();
        yield IntegerField::new('score', 'Score')->hideOnForm();

        yield BooleanField::new('isConfirmed', 'Confirmé')
            ->renderAsSwitch(false); // On utilise le bouton personnalisé à la place

        yield DateTimeField::new('confirmedAt', 'Validé le')
            ->hideOnForm();
    }

    /**
     * Méthode de validation personnalisée
     * On récupère l'ID manuellement pour éviter l'erreur EntityDto null
     */
    public function validateNotary(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        // Récupération de l'ID depuis les paramètres de la requête (URL)
        $id = $context->getRequest()->query->get('entityId');
        
        // Recherche de l'entité en base de données
        $notary = $em->getRepository(Notary::class)->find($id);

        if (!$notary instanceof Notary) {
            $this->addFlash('danger', 'Erreur : Impossible de trouver l\'étude notariale.');
            return $this->redirect($adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        // Mise à jour de l'entité
        $notary->setIsConfirmed(true);
        $notary->setConfirmedAt(new \DateTimeImmutable());

        $em->flush();

        // Notification de succès
        $this->addFlash('success', sprintf('L\'étude "%s" a été validée. Le notaire peut maintenant accéder à ses outils.', $notary->getName()));

        // Redirection vers la liste des notaires
        $url = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->setEntityId(null) // On retire l'ID de l'URL pour la redirection
            ->generateUrl();

        return $this->redirect($url);
    }
}