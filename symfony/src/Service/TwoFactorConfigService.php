<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de configuration pour l'intégration 2FA
 * 
 * Centralise tous les paramètres de configuration nécessaires
 * pour l'intégration entre Symfony et l'application legacy.
 */
class TwoFactorConfigService
{
    private string $ssoSharedSecret;
    private string $symfonyBaseUrl;
    private int $httpTimeout;
    private bool $debugMode;
    private LoggerInterface $logger;

    public function __construct(
        string $ssoSharedSecret,
        string $symfonyBaseUrl,
        int $httpTimeout,
        bool $debugMode,
        LoggerInterface $logger
    ) {
        $this->ssoSharedSecret = $ssoSharedSecret;
        $this->symfonyBaseUrl = $symfonyBaseUrl;
        $this->httpTimeout = $httpTimeout;
        $this->debugMode = $debugMode;
        $this->logger = $logger;
    }

    /**
     * Récupère la clé secrète SSO
     */
    public function getSsoSharedSecret(): string
    {
        return $this->ssoSharedSecret;
    }

    /**
     * Récupère l'URL de base de Symfony
     */
    public function getSymfonyBaseUrl(): string
    {
        return $this->symfonyBaseUrl;
    }

    /**
     * Récupère le timeout HTTP
     */
    public function getHttpTimeout(): int
    {
        return $this->httpTimeout;
    }

    /**
     * Vérifie si le mode debug est activé
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * Valide la clé secrète SSO
     */
    public function validateSsoSecret(string $providedSecret): bool
    {
        $isValid = hash_equals($this->ssoSharedSecret, $providedSecret);
        
        if ($this->debugMode) {
            $this->logger->debug('SSO Secret validation', [
                'provided' => substr($providedSecret, 0, 8) . '...',
                'expected' => substr($this->ssoSharedSecret, 0, 8) . '...',
                'valid' => $isValid
            ]);
        }
        
        return $isValid;
    }

    /**
     * Récupère l'URL complète pour l'endpoint SSO login
     */
    public function getSsoLoginUrl(): string
    {
        return $this->symfonyBaseUrl . '/symfony/api/internal/sso/login';
    }

    /**
     * Récupère l'URL complète pour l'endpoint SSO logout
     */
    public function getSsoLogoutUrl(): string
    {
        return $this->symfonyBaseUrl . '/symfony/api/internal/sso/logout';
    }

    /**
     * Récupère l'URL de la page de gestion 2FA
     */
    public function get2faManagementUrl(): string
    {
        return '/symfony/account/2fa';
    }

    /**
     * Récupère l'URL de la page de validation 2FA
     */
    public function get2faLoginUrl(): string
    {
        return '/symfony/security/2fa/login';
    }
} 
