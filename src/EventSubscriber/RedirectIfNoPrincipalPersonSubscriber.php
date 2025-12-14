<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\User; 

class RedirectIfNoPrincipalPersonSubscriber implements EventSubscriberInterface
{
    private Security $security;
    private UrlGeneratorInterface $urlGenerator;
    
    // ⚠️ Remplacez par la route exacte de création de la première personne !
    private const TARGET_ROUTE = 'app_tree_initial_person_creation'; 
    
    // Routes exactes à ignorer
    private const IGNORED_ROUTES = [
        self::TARGET_ROUTE,
        'admin',
        'app_logout',
        'app_register', 
        'app_login',
    ];

    // ⭐️ PRÉFIXES DE ROUTES QUE NOUS VOULONS TOUJOURS AUTORISER (même si profil incomplet) ⭐️
    private const AUTHORIZED_PREFIXES = [
        'app_legal_', 
        'app_error_',
        'notaire_', // <- Autorise toutes les routes qui commencent par 'notaire_'
    ];

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        
        // 1. VÉRIFICATIONS PRÉLIMINAIRES (Non connecté ou Admin)
        if (!$user || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // 2. VÉRIFICATION DU PROFIL INCOMPLET
        if (method_exists($user, 'getPeopleOwned') && $user->getPeopleOwned()->count() == 0) {
            
            $request = $event->getRequest();
            $currentRoute = $request->attributes->get('_route');

            // 3. VÉRIFICATION DE L'IGNORANCE (Routes exactes)
            if ($currentRoute === null || in_array($currentRoute, self::IGNORED_ROUTES)) {
                return;
            }

            // ⭐️ 4. VÉRIFICATION DE L'IGNORANCE (Préfixes) ⭐️
            // Si la route est 'notaire_dashboard' ou 'app_legal_cgu', on ignore la redirection
            foreach (self::AUTHORIZED_PREFIXES as $prefix) {
                if (str_starts_with($currentRoute, $prefix)) {
                    return;
                }
            }
            
            // 5. REDIRECTION FORCÉE (L'utilisateur tente d'accéder à une route non autorisée)
            // L'utilisateur est forcé vers la création de personne
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
            // Priorité élevée pour être sûr d'intercepter la requête avant le contrôleur
            KernelEvents::CONTROLLER => ['onKernelController', 10], 
        ];
    }
}