<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class BridgeLoginController extends AbstractController
{
    #[Route('/bridge-login', name: 'app_bridge_login')]
    public function index(Request $request, LoggerInterface $logger): Response
    {
        $userId = $request->query->get('user_id');
        $fromActivation = $request->query->get('from_activation');
        
        $logger->info("[Bridge Login] Received request for user_id: '{$userId}'. From activation: {$fromActivation}");

        // The key part is ensuring the user is "fully" authenticated in Symfony's context.
        // For 2FA, this means the `_2fa_passed` flag must be true in the session for the 'main' firewall.
        if (!$request->getSession()->get('_security_main')) {
             $logger->warning("[Bridge Login] User '{$userId}' is not authenticated in Symfony session. Redirecting to legacy login.");
             return $this->redirect('/Web/index.php');
        }

        $sessionData = unserialize($request->getSession()->get('_security_main'));

        if (!isset($sessionData['_2fa_passed']) || !$sessionData['_2fa_passed']) {
            $logger->warning("[Bridge Login] 2FA flag not passed for user '{$userId}'. Redirecting to 2FA login.");
            return $this->redirectToRoute('app_2fa_login', ['user_id' => $userId]);
        }
        
        // If we get here, it means:
        // 1. The user is logged into Symfony.
        // 2. They have successfully passed the 2FA check.
        // It's now safe to redirect them to the legacy application's dashboard.
        
        $logger->info("[Bridge Login] User '{$userId}' successfully authenticated with 2FA. Redirecting to legacy dashboard.");
        
        // This should point to the main dashboard of the legacy application.
        return $this->redirect('/Web/dashboard.php');
    }
} 
