<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RedirectIfNoPrincipalPersonSubscriber implements EventSubscriberInterface
{
    private Security $security;
    private UrlGeneratorInterface $urlGenerator;
    
    // Route exacte de création de la première personne
    private const TARGET_ROUTE = 'app_tree_initial_person_creation'; 
    
    // Routes exactes à ignorer
    private const IGNORED_ROUTES = [
        self::TARGET_ROUTE,
        'admin',
        'app_logout',
        'app_register', 
        'app_login',
    ];

    // Préfixes de routes toujours autorisés
    private const AUTHORIZED_PREFIXES = [
        'app_legal_', 
        'app_error_',
        'notaire_', 
        'ux_entity_autocomplete', // ⭐️ INDISPENSABLE pour l'autocomplétion Symfony UX
    ];

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        // 1. NE JAMAIS REDIRIGER LES REQUÊTES AJAX / FETCH
        // L'autocomplétion est une requête de ce type. Une redirection ici casse le JavaScript.
        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        
        // 2. VÉRIFICATIONS PRÉLIMINAIRES (Non connecté ou Admin)
        if (!$user || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // 3. VÉRIFICATION DU PROFIL INCOMPLET (Aucune personne possédée dans l'arbre)
        if (method_exists($user, 'getPeopleOwned') && $user->getPeopleOwned()->count() == 0) {
            
            $currentRoute = $request->attributes->get('_route');

            // 4. VÉRIFICATION DE L'IGNORANCE (Routes exactes)
            if ($currentRoute === null || in_array($currentRoute, self::IGNORED_ROUTES)) {
                return;
            }

            // 5. VÉRIFICATION DE L'IGNORANCE (Préfixes autorisés)
            foreach (self::AUTHORIZED_PREFIXES as $prefix) {
                if (str_starts_with($currentRoute, $prefix)) {
                    return;
                }
            }
            
            // 6. REDIRECTION FORCÉE
            // L'utilisateur tente d'accéder à une page classique sans avoir créé sa fiche initiale
            $url = $this->urlGenerator->generate(self::TARGET_ROUTE);
            $response = new RedirectResponse($url);
            
            $event->setController(function() use ($response) {
                return $response;
            });
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité élevée (10) pour agir avant le chargement du contrôleur
            KernelEvents::CONTROLLER => ['onKernelController', 10], 
        ];
    }
}