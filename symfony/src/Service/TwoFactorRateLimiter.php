<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterInterface;

class TwoFactorRateLimiter
{
    private RateLimiterFactory $userLimiter;
    private RateLimiterFactory $ipLimiter;
    private LoggerInterface $logger;

    public function __construct(
        RateLimiterFactory $twoFactorAuthLimiter,
        RateLimiterFactory $twoFactorAuthIpLimiter,
        LoggerInterface $logger
    ) {
        $this->userLimiter = $twoFactorAuthLimiter;
        $this->ipLimiter = $twoFactorAuthIpLimiter;
        $this->logger = $logger;
    }

    /**
     * Vérifie si l'utilisateur peut tenter une authentification 2FA
     */
    public function canAttempt(string $userId, Request $request): bool
    {
        $ipAddress = $request->getClientIp();
        
        // Vérifier la limite par utilisateur
        $userLimiter = $this->userLimiter->create($userId);
        $userLimit = $userLimiter->consume(1);
        
        // Vérifier la limite par IP
        $ipLimiter = $this->ipLimiter->create($ipAddress);
        $ipLimit = $ipLimiter->consume(1);
        
        $userBlocked = !$userLimit->isAccepted();
        $ipBlocked = !$ipLimit->isAccepted();
        
        if ($userBlocked) {
            $this->logger->warning(sprintf(
                '[2FA Rate Limit] User %s blocked. Remaining time: %d seconds',
                $userId,
                $userLimit->getRetryAfter()->getTimestamp() - time()
            ));
        }
        
        if ($ipBlocked) {
            $this->logger->warning(sprintf(
                '[2FA Rate Limit] IP %s blocked. Remaining time: %d seconds',
                $ipAddress,
                $ipLimit->getRetryAfter()->getTimestamp() - time()
            ));
        }
        
        return $userLimit->isAccepted() && $ipLimit->isAccepted();
    }

    /**
     * Enregistre une tentative réussie (reset le compteur)
     */
    public function recordSuccess(string $userId, Request $request): void
    {
        $ipAddress = $request->getClientIp();
        
        // Reset les limiteurs pour cet utilisateur et cette IP
        $userLimiter = $this->userLimiter->create($userId);
        $ipLimiter = $this->ipLimiter->create($ipAddress);
        
        // Consommer 0 tokens pour reset le compteur
        $userLimiter->consume(0);
        $ipLimiter->consume(0);
        
        $this->logger->info(sprintf(
            '[2FA Rate Limit] Success recorded for user %s (IP: %s), limiters reset',
            $userId,
            $ipAddress
        ));
    }

    /**
     * Obtient le temps d'attente restant pour un utilisateur
     */
    public function getRetryAfter(string $userId, Request $request): ?int
    {
        $ipAddress = $request->getClientIp();
        
        $userLimiter = $this->userLimiter->create($userId);
        $ipLimiter = $this->ipLimiter->create($ipAddress);
        
        $userLimit = $userLimiter->consume(0);
        $ipLimit = $ipLimiter->consume(0);
        
        $userRetryAfter = $userLimit->getRetryAfter()?->getTimestamp();
        $ipRetryAfter = $ipLimit->getRetryAfter()?->getTimestamp();
        
        if ($userRetryAfter && $ipRetryAfter) {
            return max($userRetryAfter, $ipRetryAfter) - time();
        }
        
        return $userRetryAfter ? $userRetryAfter - time() : ($ipRetryAfter ? $ipRetryAfter - time() : null);
    }

    /**
     * Obtient le nombre de tentatives restantes pour un utilisateur
     */
    public function getRemainingAttempts(string $userId, Request $request): array
    {
        $ipAddress = $request->getClientIp();
        
        $userLimiter = $this->userLimiter->create($userId);
        $ipLimiter = $this->ipLimiter->create($ipAddress);
        
        $userLimit = $userLimiter->consume(0);
        $ipLimit = $ipLimiter->consume(0);
        
        return [
            'user_remaining' => $userLimit->getRemainingTokens(),
            'ip_remaining' => $ipLimit->getRemainingTokens(),
            'user_limit' => $userLimit->getLimit(),
            'ip_limit' => $ipLimit->getLimit(),
        ];
    }
} 
