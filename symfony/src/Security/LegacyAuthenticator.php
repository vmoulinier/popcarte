<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LegacyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): ?bool
    {
        // This authenticator is only for loading the user from the session,
        // it does not "support" any request to actively authenticate.
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        // This is never called because supports() returns false.
        // The method still needs to be here to satisfy the interface.
        throw new \LogicException('This method should not be called.');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // on failure, let the request continue
        return null;
    }

    /**
     * This is called when an anonymous user tries to access a protected page.
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // Redirect them to the legacy login page.
        return new RedirectResponse('/Web/index.php');
    }
} 
