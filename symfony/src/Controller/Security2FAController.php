<?php

namespace App\Controller;

use App\Repository\User2FARepository;
use App\Service\LegacySessionManager;
use App\Service\TwoFactorRateLimiter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User2FA;

class Security2FAController extends AbstractController
{
    private $logger;
    private $rateLimiter;

    public function __construct(LoggerInterface $logger, TwoFactorRateLimiter $rateLimiter)
    {
        $this->logger = $logger;
        $this->rateLimiter = $rateLimiter;
    }

    #[Route('/security/2fa/login', name: 'app_security_2fa_login')]
    public function login2FA(
        Request $request,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        // 1) Tenter de récupérer l’utilisateur depuis la session Symfony
        $sessionUser = $this->getUser();

        if ($sessionUser) {
            $lookupId = (int) $sessionUser->getId();
            $userId   = (string) $lookupId;
            $this->logger->info(sprintf('[2FA Login] Utilisateur authentifié via session Symfony, id=%d', $lookupId));
        } else {
            // 2) Fallback : on accepte encore le paramètre user_id pour compatibilité
            $userId = $request->query->get('user_id', $request->request->get('user_id'));

            if (!$userId) {
                $this->logger->warning('[2FA Login] Page accessed without authenticated user or user_id param.');
                return $this->redirect('/Web/index.php');
            }

            // Recherche via Doctrine plutôt qu'avec du SQL brut
            if (is_numeric($userId)) {
                $user = $userRepository->find($userId);
            } else {
                $user = $userRepository->findOneBy(['username' => $userId]);
                if (!$user) {
                    $user = $userRepository->findOneBy(['email' => $userId]);
                }
            }

            if (!$user) {
                $this->logger->error(sprintf('[2FA Login] Utilisateur "%s" introuvable.', $userId));
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirect('/Web/index.php');
            }

            $lookupId = $user->getId();
        }

        $user2fa = $em->getRepository(User2FA::class)->findOneBy(['userId' => $lookupId]);

        if (!$user2fa || !$user2fa->isEnabled()) {
            $this->logger->error(sprintf('[2FA Login] 2FA not configured or disabled for user_id: "%s" (numeric: %d).', $userId, $lookupId));
            $this->addFlash('error', 'Le 2FA n\'est pas activé pour ce compte.');
            return $this->redirect('/Web/index.php');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            
            // Vérifier le rate limiting avant de valider le code
            if (!$this->rateLimiter->canAttempt($lookupId, $request)) {
                $retryAfter = $this->rateLimiter->getRetryAfter($lookupId, $request);
                $minutes = ceil($retryAfter / 60);
                
                $this->logger->warning(sprintf('[2FA Login] Rate limit exceeded for user_id: %s. Retry after %d minutes.', $lookupId, $minutes));
                $this->addFlash('error', sprintf('Trop de tentatives échouées. Veuillez réessayer dans %d minutes.', $minutes));
                
                return $this->render('security/2fa_login.html.twig', [
                    'user_id' => $userId,
                    'rate_limited' => true,
                    'retry_after_minutes' => $minutes,
                ]);
            }
            
            if ($totpAuthenticator->checkCode($user2fa, $code)) {
                $this->logger->info(sprintf('[2FA Login] Code is valid for userId \'%s\'. Generating token.', $lookupId));
                
                // Enregistrer le succès et reset le rate limiter
                $this->rateLimiter->recordSuccess($lookupId, $request);

                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $user2fa->setTempLoginSecret($token);
                $user2fa->setTempLoginExpiresAt(new \DateTimeImmutable('+5 minutes'));
                $em->flush();

                return $this->render('security/auto_post_redirect.html.twig', [
                    'login_token' => $token,
                ]);
            } else {
                $this->logger->warning(sprintf('[2FA Login] Invalid 2FA code for user_id: %s.', $lookupId));
                
                // Obtenir le nombre de tentatives restantes
                $attempts = $this->rateLimiter->getRemainingAttempts($lookupId, $request);
                $remaining = min($attempts['user_remaining'], $attempts['ip_remaining']);
                
                if ($remaining <= 0) {
                    $retryAfter = $this->rateLimiter->getRetryAfter($lookupId, $request);
                    $minutes = ceil($retryAfter / 60);
                    $this->addFlash('error', sprintf('Trop de tentatives échouées. Veuillez réessayer dans %d minutes.', $minutes));
                } else {
                    $this->addFlash('error', sprintf('Code invalide. Il vous reste %d tentative(s).', $remaining));
                }
            }
        }

        return $this->render('security/2fa_login.html.twig', [
            'user_id' => $userId, // Pass original identifier back to form
        ]);
    }
}
