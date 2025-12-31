<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class UserOnboardingSubscriber implements EventSubscriberInterface
{
    // Liste des routes qui sont AUTORISÉES même sans famille
    private const ALLOWED_ROUTES = [
        'app_person_new', // Création d'un membre
        'app_logout', // Deconnexion
        'app_login', // Connexion
        'app_register', // Inscription
        'app_register_notary', // Inscription notaire
        'app_home', // Accueil
    ];

    public function __construct(
        private Security $security,
        private RouterInterface $router
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');

        // 1. On ignore si ce n'est pas une route (ex: assets) ou si c'est une route autorisée
        if (
            !$routeName ||
            in_array($routeName, self::ALLOWED_ROUTES) ||
            str_starts_with($routeName, '_') ||
            str_starts_with($routeName, 'app_site_')
        ) {
            return;
        }

        // 2. On récupère l'utilisateur connecté
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (in_array('ROLE_NOTARY', $user->getRoles())) {
            return; // On laisse le notaire circuler librement
        }

        // 3. LOGIQUE DE BLOCAGE
        // Si l'utilisateur n'a personne dans sa famille
        if ($user->getPeople()->isEmpty()) {

            // Optionnel : Ajouter un message flash pour expliquer pourquoi il est redirigé
            $request->getSession()->getFlashBag()->add('info', 'Veuillez créer le premier membre de votre famille pour continuer.');

            // Redirection forcée vers la création
            $response = new RedirectResponse($this->router->generate('app_person_new'));
            $event->setResponse($response);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // On met une priorité basse (0) pour laisser le firewall de sécurité passer avant
            KernelEvents::REQUEST => [['onKernelRequest', 0]],
        ];
    }
}
