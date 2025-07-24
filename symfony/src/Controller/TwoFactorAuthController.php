<?php

namespace App\Controller;

use App\Repository\User2FARepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TwoFactorAuthController extends AbstractController
{
    #[Route('/check-2fa-status', name: 'app_check_2fa_status')]
    public function checkStatus(
        Request $request,
        EntityManagerInterface $em,
        User2FARepository $user2FARepository,
        LoggerInterface $logger
    ): Response {
        // 1) Utilisateur authentifié via Symfony
        $sessionUser = $this->getUser();

        if ($sessionUser) {
            $lookupId = (int) $sessionUser->getId();
            $userId   = (string) $lookupId;
            $logger->info("[2FA Check-Status] Session Symfony détectée pour user_id '{$lookupId}'.");
        } else {
            // 2) Fallback : paramètre user_id
            $userId = $request->query->get('user_id');
            $logger->info("[2FA Check-Status] Requête avec param user_id='{$userId}'");

            if (!$userId) {
                return new Response('User ID required', Response::HTTP_BAD_REQUEST);
            }

            // Conversion en ID numérique si nécessaire
            if (!is_numeric($userId)) {
                $connection = $em->getConnection();
                $escapedIdentifier = $connection->quote($userId);
                $sql = "SELECT user_id FROM users WHERE username = $escapedIdentifier OR email = $escapedIdentifier";
                $stmt = $connection->prepare($sql);
                $result = $stmt->executeQuery();
                $user = $result->fetchAssociative();

                if (!$user) {
                    $logger->warning("[2FA Check-Status] Legacy user '{$userId}' not found. Considering 2FA not configured.");
                    return new Response('User not found, 2FA not configured.', Response::HTTP_NOT_FOUND);
                }
                $lookupId = $user['user_id'];
            } else {
                $lookupId = $userId;
            }
        }

        $user2fa = $user2FARepository->findOneBy(['userId' => $lookupId]);

        if (!$user2fa) {
            $logger->info("[2FA Check-Status] No User2FA record for userId '{$lookupId}'. Status: NOT_CONFIGURED (404)");
            return new Response('2FA not configured', Response::HTTP_NOT_FOUND);
        }

        if ($user2fa->isEnabled()) {
            $logger->info("[2FA Check-Status] User2FA enabled for userId '{$lookupId}'. Status: ENABLED (200)");
            return new Response('2FA enabled', Response::HTTP_OK);
        }
        
        $logger->info("[2FA Check-Status] User2FA disabled for userId '{$lookupId}'. Status: DISABLED (204)");
        // Configured but disabled. The legacy app should handle this by just logging in.
        return new Response('', Response::HTTP_NO_CONTENT);
    }
} 
