<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setPageTitle('index', 'Liste des Utilisateurs')
            ->setSearchFields(['email', 'city', 'postalCode', 'roles'])
            // Afficher le statut Notaire en attente en haut de la liste
            ->setDefaultSort(['isActived' => 'ASC', 'email' => 'ASC']); 
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield EmailField::new('email');
        
        // Champs d'information de localisation
        yield TextField::new('city', 'Ville')
            ->hideOnIndex(false);

        yield TextField::new('postalCode', 'Code Postal')
            ->hideOnIndex(true);
        
        // ⭐️ CHAMP CRUCIAL 1 : Gérer le rôle ⭐️
        yield ArrayField::new('roles', 'Rôles')
            ->setHelp('Pour valider un notaire, changez ROLE_NOTAIRE_PENDING par ROLE_NOTAIRE.'); 
            
        // ⭐️ CHAMP CRUCIAL 2 : Activation du compte ⭐️
        yield BooleanField::new('isActived', 'Compte Actif')
            ->setHelp('Le compte doit être Actif (OUI) pour permettre la connexion à l\'utilisateur.')
            // Utilisation d'un Badget pour afficher clairement le statut en index
            ->renderAsSwitch(true);
            
        // Pour les Notaires, cela permet de s'assurer qu'ils ont été vérifiés.
    }
    
    public function configureActions(Actions $actions): Actions
    {
        // ⭐️ ACTION PERSONNALISÉE : Pour marquer un compte comme "Approuvé" en 1 clic ⭐️
        $approveAction = Action::new('approveNotaire', 'Approuver Notaire')
            ->displayIf(static function (User $user) {
                // N'affiche l'action que si l'utilisateur est en attente (ROLE_NOTAIRE_PENDING)
                return in_array('ROLE_NOTAIRE_PENDING', $user->getRoles());
            })
            // L'action sera traitée par une méthode dans ce contrôleur
            ->linkToCrudAction('handleApproveNotaire') 
            ->addCssClass('btn btn-success');

        return $actions
            ->add(Crud::PAGE_INDEX, $approveAction) // Ajout de l'action dans la liste
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setLabel('Supprimer');
            });
    }

    /**
     * Méthode pour gérer l'action "Approuver Notaire" (un clic)
     */
    public function handleApproveNotaire(AdminContext $context, EntityManagerInterface $entityManager)
    {
        // 1. Récupérer l'ID de l'entité depuis le contexte
        $entityId = $context->getRequest()->query->get('entityId');
        
        if (!$entityId) {
            $this->addFlash('danger', 'Erreur: ID de l\'utilisateur manquant.');
            return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
        }

        // 2. Récupérer l'objet User complet
        $user = $entityManager->getRepository(User::class)->find($entityId);

        if (!$user) {
            $this->addFlash('danger', sprintf('Erreur: Utilisateur avec l\'ID "%s" introuvable.', $entityId));
            return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
        }

        // 3. Traitement de l'approbation (Logique Métier)
        
        // Vérification de sécurité supplémentaire
        if (!in_array('ROLE_NOTAIRE_PENDING', $user->getRoles())) {
            $this->addFlash('warning', 'Ce compte n\'est pas en statut d\'attente de Notaire et n\'a pas été modifié.');
            return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
        }
        
        // Changer le rôle provisoire et activer
        $user->setRoles(['ROLE_NOTAIRE']);
        $user->setIsActived(true);
        
        // 4. Persister les changements
        $entityManager->flush();

        $this->addFlash('success', sprintf('Le compte Notaire %s a été approuvé et est maintenant actif.', $user->getEmail()));

        // Rediriger vers la page de liste (le referrer)
        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }
}