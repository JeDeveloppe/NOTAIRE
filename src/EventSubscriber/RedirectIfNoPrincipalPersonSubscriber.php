<?php

namespace App\EventSubscriber;

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
    
    // ⚠️ Remplacez par la route exacte de création de la première personne !
    private const TARGET_ROUTE = 'app_tree_initial_person_creation'; 
    
    // Routes à ignorer pour éviter une boucle de redirection infinie
    private const IGNORED_ROUTES = [
        self::TARGET_ROUTE,
        'app_logout',
        'app_register', // Si l'on veut éviter de rediriger l'utilisateur juste après l'inscription
        'app_login',    // Si l'on veut éviter de rediriger l'utilisateur juste après la déconnexion
    ];

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $user = $this->security->getUser();
        
        // 1. Vérifier si l'utilisateur est connecté ET s'il n'a AUCUNE Personne enregistrée.
        // Utilisation de la relation peopleOwned sur l'entité User.
        if (
            $user && 
            method_exists($user, 'getPeopleOwned') && 
            $user->getPeopleOwned()->count() == 0 // ⬅️ VÉRIFICATION OPTIMISÉE
        ) {
            
            $request = $event->getRequest();
            $currentRoute = $request->attributes->get('_route');

            // 2. Vérifier que la route actuelle n'est pas la page cible ou une page ignorée
            if (!in_array($currentRoute, self::IGNORED_ROUTES)) {
                
                // 3. Redirection forcée
                $url = $this->urlGenerator->generate(self::TARGET_ROUTE);
                $response = new RedirectResponse($url);
                
                // Stopper l'exécution du contrôleur initial et exécuter la redirection
                $event->setController(function() use ($response) {
                    return $response;
                });
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 10], 
        ];
    }
}