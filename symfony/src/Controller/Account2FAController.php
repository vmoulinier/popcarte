<?php

namespace App\Controller;

use App\Entity\User2FA;
use App\Repository\User2FARepository;
use App\Service\TwoFactorRateLimiter;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/account/2fa')]
class Account2FAController extends AbstractController
{
    private LoggerInterface $logger;
    private TwoFactorRateLimiter $rateLimiter;

    public function __construct(LoggerInterface $logger, TwoFactorRateLimiter $rateLimiter)
    {
        $this->logger = $logger;
        $this->rateLimiter = $rateLimiter;
    }

    #[Route('', name: 'app_account_2fa', methods: ['GET'])]
    public function index(
        EntityManagerInterface $em,
        TotpAuthenticatorInterface $totpAuthenticator,
        User2FARepository $user2FARepository
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            $this->logger->warning('[2FA Index] No authenticated user found, redirecting to legacy login.');
            return $this->redirect('/Web/index.php');
        }

        $lookupId = $user->getId();
        $this->logger->info("[2FA Index] Managing 2FA for authenticated user_id: '{$lookupId}'");

        $user2fa = $user2FARepository->findOneBy(['userId' => $lookupId]);

        if (!$user2fa) {
            $this->logger->info("[2FA Index] No existing User2FA record found for userId '{$lookupId}'. Creating a new one.");
            $user2fa = new User2FA();
            $user2fa->setUserId((string)$lookupId);
            $secret = $totpAuthenticator->generateSecret();
            $user2fa->setSecret($secret);
            $em->persist($user2fa);
            $em->flush();
            $this->logger->info("[2FA Index] New User2FA record persisted for userId '{$lookupId}'.");
        } else {
            $this->logger->info("[2FA Index] Found existing User2FA record for userId '{$lookupId}'.");
        }

        $qrCodeDataUri = null;
        try {
            $qrCodeContent = $totpAuthenticator->getQRContent($user2fa);

            // Revert to the previously working syntax for this library version, using named arguments.
            $qrCode = new QrCode(
                data: $qrCodeContent,
                size: 200,
                margin: 0
            );

            $writer = new PngWriter();
            $qrCodeDataUri = $writer->write($qrCode)->getDataUri();
        } catch (\Exception $e) {
            $this->logger->error('[2FA Index] QR Code generation failed: ' . $e->getMessage());
            $this->addFlash('error', 'Erreur lors de la génération du QR code.');
        }


        return $this->render('account/2fa.html.twig', [
            'qr_code' => $qrCodeDataUri,
            'user_id' => $lookupId, // Pass ID to the form for activate/disable
            'user2fa' => $user2fa,
            'secret' => $user2fa->getSecret(),
            'is_enabled' => $user2fa->isEnabled(),
        ]);
    }

    #[Route('/activate', name: 'app_account_2fa_activate', methods: ['POST'])]
    public function activate(
        Request $request,
        User2FARepository $user2FARepository,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $em
    ): Response {
        $userId = $request->request->get('user_id');
        $code = $request->request->get('code');
        $this->logger->info("[2FA Activate] Received activation request for user_id: '{$userId}'");

        // If the identifier is not numeric, we convert it to look up in the DB
        if (!is_numeric($userId)) {
            $connection = $em->getConnection();
            $escapedIdentifier = $connection->quote($userId);
            $sql = "SELECT user_id FROM users WHERE username = $escapedIdentifier OR email = $escapedIdentifier";
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeQuery();
            $user = $result->fetchAssociative();

            if (!$user) {
                $this->logger->error("[2FA Activate] Legacy user '{$userId}' not found in DB.");
                $this->addFlash('error', "Utilisateur legacy '$userId' non trouvé.");
                return $this->redirect('/Web/index.php');
            }

            $lookupId = $user['user_id'];
            $this->logger->info("[2FA Activate] Converted user_id '{$userId}' to numeric id: '{$lookupId}'");
        } else {
            $lookupId = $userId;
            $this->logger->info("[2FA Activate] Received numeric user_id: '{$lookupId}'");
        }

        $this->logger->info('[2FA Activate] Attempting to find User2FA record for userId: \'' . $lookupId . '\'');
        $user2fa = $em->getRepository(User2FA::class)->findOneBy(['userId' => $lookupId]);

        if (!$user2fa) {
            $this->logger->error(sprintf('[2FA Activate] User2FA record not found for userId: \'%s\', cannot activate.', $lookupId));
            $this->addFlash('error', 'Impossible de trouver les informations 2FA pour cet utilisateur.');
            return $this->redirectToRoute('app_account_2fa', ['user_id' => $userId]);
        }

        $this->logger->info('[2FA Activate] Found User2FA record. Validating code.');

        // Vérifier le rate limiting avant de valider le code
        if (!$this->rateLimiter->canAttempt($lookupId, $request)) {
            $retryAfter = $this->rateLimiter->getRetryAfter($lookupId, $request);
            $minutes = ceil($retryAfter / 60);
            
            $this->logger->warning(sprintf('[2FA Activate] Rate limit exceeded for user_id: %s. Retry after %d minutes.', $lookupId, $minutes));
            $this->addFlash('error', sprintf('Trop de tentatives échouées. Veuillez réessayer dans %d minutes.', $minutes));
            
            return $this->redirectToRoute('app_account_2fa', ['user_id' => $userId]);
        }

        if ($totpAuthenticator->checkCode($user2fa, $code)) {
            $this->logger->info(sprintf('[2FA Activate] Code is valid for userId \'%s\'. Activating and generating token.', $lookupId));
            
            // Enregistrer le succès et reset le rate limiter
            $this->rateLimiter->recordSuccess($lookupId, $request);

            $user2fa->setEnabled(true);
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $user2fa->setTempLoginSecret($token);
            $user2fa->setTempLoginExpiresAt(new \DateTimeImmutable('+5 minutes'));
            $em->flush();

            return $this->render('security/auto_post_redirect.html.twig', [
                'login_token' => $token,
            ]);
        }

        $this->logger->warning(sprintf('[2FA Activate] Invalid code provided for userId \'%s\'.', $lookupId));
        
        // Obtenir le nombre de tentatives restantes
        $attempts = $this->rateLimiter->getRemainingAttempts($lookupId, $request);
        $remaining = min($attempts['user_remaining'], $attempts['ip_remaining']);
        
        if ($remaining <= 0) {
            $retryAfter = $this->rateLimiter->getRetryAfter($lookupId, $request);
            $minutes = ceil($retryAfter / 60);
            $this->addFlash('error', sprintf('Trop de tentatives échouées. Veuillez réessayer dans %d minutes.', $minutes));
        } else {
            $this->addFlash('error', sprintf('Le code de vérification est incorrect. Il vous reste %d tentative(s).', $remaining));
        }
        
        return $this->redirectToRoute('app_account_2fa', ['user_id' => $userId]);
    }

    #[Route('/disable', name: 'app_account_2fa_disable', methods: ['POST'])]
    public function disable(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirect('/Web/index.php');
        }
        $lookupId = $user->getId();

        $this->logger->info("[2FA Disable] Received disable request for user_id: '{$lookupId}'");

        $user2fa = $em->getRepository(User2FA::class)->findOneBy(['userId' => $lookupId]);

        if ($user2fa) {
            $this->logger->info("[2FA Disable] Disabling 2FA for userId '{$lookupId}'.");
            $user2fa->setEnabled(false);
            $user2fa->setTempLoginSecret(null);
            $user2fa->setTempLoginExpiresAt(null);
            $em->flush();
        } else {
            $this->logger->warning("[2FA Disable] No User2FA record found for userId '{$lookupId}'. Cannot disable.");
            $this->addFlash('warning', '2FA non trouvé pour cet utilisateur.');
        }

        return $this->redirectToRoute('app_account_2fa');
    }
}
