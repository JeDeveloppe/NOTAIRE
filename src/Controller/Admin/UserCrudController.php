<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Dom\Text;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField; // 💡 NOUVEAU : Pour afficher les relations
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField; // Optionnel : pour un affichage plus propre des rôles
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Validator\Constraints\Date;

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
            ->setSearchFields(['email', 'city.name', 'roles']) // 💡 CORRECTION : Recherche sur le nom de la ville
            // Afficher le statut Notaire en attente en haut de la liste
            ->setDefaultSort(['isActived' => 'ASC', 'email' => 'ASC']); 
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'Adresse Email');
        yield TextField::new('uniqueCode', 'Code Unique');
        yield DateTimeField::new('codeExpiresAt', 'Code expire le')->setTimezone('Europe/Paris')->setDisabled(true)->setFormat('dd/MM/yyyy HH:mm');
        
        // ⭐️ CORRECTION 1 : Utilisation de AssociationField pour la relation City ⭐️
        yield AssociationField::new('city', 'Ville')
            // Par défaut, EasyAdmin utilisera la méthode __toString() de l'entité City
            ->autocomplete();
        
        // ⭐️ CHAMP CRUCIAL 1 : Gérer le rôle ⭐️
        // ArrayField est acceptable pour la modification, mais ChoiceField est mieux pour l'affichage en index.
        yield ArrayField::new('roles', 'Rôles')
            ->setHelp('Pour valider un notaire, changez ROLE_NOTAIRE_PENDING par ROLE_NOTAIRE.'); 
            
        // ⭐️ CHAMP CRUCIAL 2 : Activation du compte ⭐️
        yield BooleanField::new('isActived', 'Compte Actif')
            ->setHelp('Le compte doit être Actif (OUI) pour permettre la connexion à l\'utilisateur.')
            // Gardons renderAsSwitch pour la modification rapide, c'est efficace.
            ->renderAsSwitch(true);
            
        // Optionnel: Afficher les relations Actes et Personnes si nécessaire
        /*
        yield AssociationField::new('peopleOwned', 'Personnes rattachées')
            ->onlyOnIndex();
        
        yield AssociationField::new('acts', 'Actes en cours')
            ->onlyOnIndex();
        */
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
            })
            // Ajout du bouton d'approbation sur la page de détail/édition
            ->add(Crud::PAGE_DETAIL, $approveAction)
            ->add(Crud::PAGE_EDIT, $approveAction);
    }

    /**
     * Méthode pour gérer l'action "Approuver Notaire" (un clic)
     */
    public function handleApproveNotaire(AdminContext $context, EntityManagerInterface $entityManager)
    {
        // 1. Récupérer l'objet User via le contexte EasyAdmin (méthode plus propre)
        /** @var User $user */
        $user = $context->getEntity()->getInstance();
        
        if (!$user) {
            $this->addFlash('danger', 'Erreur: Utilisateur introuvable.');
            return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
        }

        // 2. Traitement de l'approbation (Logique Métier)
        
        // Vérification de sécurité supplémentaire
        if (!in_array('ROLE_NOTAIRE_PENDING', $user->getRoles())) {
            $this->addFlash('warning', 'Ce compte n\'est pas en statut d\'attente de Notaire et n\'a pas été modifié.');
            return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
        }
        
        // Changer le rôle provisoire et activer
        $user->setRoles(['ROLE_NOTAIRE']);
        $user->setIsActived(true);
        
        // 3. Persister les changements
        $entityManager->flush();

        $this->addFlash('success', sprintf('Le compte Notaire %s a été approuvé et est maintenant actif.', $user->getEmail()));

        // Rediriger vers la page de liste (le referrer)
        return $this->redirect($context->getReferrer() ?? $this->generateUrl('admin'));
    }
}