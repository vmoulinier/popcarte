<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\LegacyAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class SsoController extends AbstractController
{
    private string $ssoSharedSecret;

    public function __construct(string $ssoSharedSecret)
    {
        $this->ssoSharedSecret = $ssoSharedSecret;
    }

    #[Route('/api/internal/sso/login', name: 'app_sso_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        LoggerInterface $logger,
        Security $security,
        LegacyAuthenticator $authenticator
    ): Response {
        if ($request->headers->get('X-SSO-TOKEN') !== $this->ssoSharedSecret) {
            $logger->warning('[SSO Login] Unauthorized attempt: missing or invalid shared secret.');
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $userId = $request->request->get('user_id');
        $logger->info(sprintf('[SSO Login] Received login request for user_id: %s', $userId));

        if (empty($userId)) {
            return $this->json(['status' => 'error', 'message' => 'Missing user_id'], Response::HTTP_BAD_REQUEST);
        }

        if (is_numeric($userId)) {
            $user = $userRepository->find($userId);
        } else {
            $user = $userRepository->findOneBy(['username' => $userId]);
            if (!$user) {
                $user = $userRepository->findOneBy(['email' => $userId]);
            }
        }
        
        if (!$user) {
             return $this->json(['status' => 'error', 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Connecter l'utilisateur (la méthode moderne et correcte)
        $security->login($user, $authenticator::class);

        $logger->info(sprintf('[SSO Login] Symfony session created for user %s', $user->getEmail()));

        return $this->json(['status' => 'ok', 'message' => 'Symfony session created']);
    }

    #[Route('/api/internal/sso/logout', name: 'app_sso_logout', methods: ['POST'])]
    public function logout(Request $request, LoggerInterface $logger, Security $security): Response
    {
        if ($request->headers->get('X-SSO-TOKEN') !== $this->ssoSharedSecret) {
            $logger->warning('[SSO Logout] Unauthorized attempt: missing or invalid shared secret.');
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $userId = $request->request->get('user_id');
        $logger->info(sprintf('[SSO Logout] Received logout request for user_id: %s', $userId));

        // Déconnecter l'utilisateur (la méthode moderne et correcte)
        $security->logout(false); // false pour ne pas invalider toute la session, juste le token

        $logger->info(sprintf('[SSO Logout] Symfony session invalidated for user_id: %s', $userId));

        return $this->json(['status' => 'ok', 'message' => 'Symfony session invalidated or was not active']);
    }
} 
