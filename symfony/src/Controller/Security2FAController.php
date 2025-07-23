<?php

namespace App\Controller;

use App\Repository\User2FARepository;
use App\Service\LegacySessionManager;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User2FA;

class Security2FAController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/security/2fa/login', name: 'app_security_2fa_login')]
    public function login2FA(
        Request $request,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $em
    ): Response {
        $userId = $request->query->get('user_id', $request->request->get('user_id'));
        if (!$userId) {
            $this->logger->warning('[2FA Login] Page accessed without user_id.');
            return $this->redirect('/Web/index.php');
        }

        $connection = $em->getConnection();
        if (!is_numeric($userId)) {
            $this->logger->info(sprintf('[2FA Login] Received non-numeric user_id: "%s". Converting to numeric ID.', $userId));
            $escapedIdentifier = $connection->quote($userId);
            $sql = "SELECT user_id FROM users WHERE username = $escapedIdentifier OR email = $escapedIdentifier";
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeQuery();
            $userRow = $result->fetchAssociative();
            if (!$userRow) {
                $this->logger->error(sprintf('[2FA Login] Could not find numeric ID for identifier: "%s".', $userId));
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirect('/Web/index.php');
            }
            $lookupId = $userRow['user_id'];
            $this->logger->info(sprintf('[2FA Login] Converted identifier "%s" to numeric ID: "%s".', $userId, $lookupId));
        } else {
            $lookupId = (int)$userId;
        }

        $user2fa = $em->getRepository(User2FA::class)->findOneBy(['userId' => $lookupId]);

        if (!$user2fa || !$user2fa->isEnabled()) {
            $this->logger->error(sprintf('[2FA Login] 2FA not configured or disabled for user_id: "%s" (numeric: %d).', $userId, $lookupId));
            $this->addFlash('error', 'Le 2FA n\'est pas activé pour ce compte.');
            return $this->redirect('/Web/index.php');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            if ($totpAuthenticator->checkCode($user2fa, $code)) {
                $this->logger->info(sprintf('[2FA Login] Code is valid for userId \'%s\'. Generating token.', $lookupId));

                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $user2fa->setTempLoginSecret($token);
                $user2fa->setTempLoginExpiresAt(new \DateTimeImmutable('+5 minutes'));
                $em->flush();

                return $this->render('security/auto_post_redirect.html.twig', [
                    'login_token' => $token,
                ]);
            } else {
                $this->logger->warning(sprintf('[2FA Login] Invalid 2FA code for user_id: %s.', $lookupId));
                $this->addFlash('error', 'Code invalide. Veuillez réessayer.');
            }
        }

        return $this->render('security/2fa_login.html.twig', [
            'user_id' => $userId, // Pass original identifier back to form
        ]);
    }
}
