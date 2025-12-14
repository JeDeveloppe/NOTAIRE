<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class UserAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->getPayload()->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        /** @var User $user */
        $user = $token->getUser();
        
        // 1. VÉRIFICATION ET REDIRECTION SPÉCIFIQUE DU NOTAIRE
        // On utilise l'AuthorizationChecker pour vérifier si l'utilisateur a le rôle.
        if ($this->authorizationChecker->isGranted('ROLE_NOTAIRE', $user)) {
            // ⭐️ Rediriger vers la route spécifique aux Notaires ⭐️
            // REMPLACEZ 'app_notaire_dashboard' par le nom de votre route pour les notaires
            return new RedirectResponse($this->urlGenerator->generate('notaire_dashboard'));
        }
        
        // 2. REDIRECTION DU CLIENT/UTILISATEUR STANDARD
        
        // Vérifiez si l'utilisateur doit compléter son profil de personne initiale
        if ($user->getPeopleOwned()->isEmpty()) {
            // Si l'utilisateur n'a pas de personne associée, on le redirige vers le parcours initial
            return new RedirectResponse($this->urlGenerator->generate('app_tree_initial_person_creation'));
        }
        
        // 3. Redirection par défaut (par exemple, vers le tableau de bord standard ou la liste des actes)
        // REMPLACEZ 'app_default_dashboard' par la route de votre tableau de bord général
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
