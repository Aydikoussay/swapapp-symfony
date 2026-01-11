<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Authentication logic
     */
    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('_csrf_token');

        // Save last username entered
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    /**
     * Redirection après succès
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirection vers la page cible si existante
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Récupérer le user connecté
        $user = $token->getUser();

        // Redirection selon rôle
        if (in_array("ROLE_ADMIN", $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('chart'));
        }

        if (in_array("ROLE_USER", $user->getRoles(), true)) {
            if (!method_exists($user, 'getId')) {
                throw new \LogicException('The user object must have a getId method.');
            }

            // Ensure the user is cast to the correct class if necessary
            if (!$user instanceof \App\Entity\User) {
                throw new \LogicException('The user object must be an instance of App\Entity\User.');
            }

            return new RedirectResponse(
                $this->urlGenerator->generate('app_user_edit', [
                    'id' => $user->getId()
                ])
            );
        }

        throw new \Exception('No valid redirect found in ' . __FILE__);
    }

    /**
     * URL de la page login
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

