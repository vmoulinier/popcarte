<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de validation des tokens SSO
 * 
 * Valide les tokens d'authentification SSO entre le legacy et Symfony
 */
class SsoTokenValidator
{
    private string $ssoSharedSecret;
    private LoggerInterface $logger;

    public function __construct(string $ssoSharedSecret, LoggerInterface $logger)
    {
        $this->ssoSharedSecret = $ssoSharedSecret;
        $this->logger = $logger;
    }

    /**
     * Valide un token SSO
     * 
     * @param string|null $providedToken Le token fourni dans la requête
     * @return bool True si le token est valide, false sinon
     */
    public function validateToken(?string $providedToken): bool
    {
        if (empty($providedToken)) {
            $this->logger->warning('SSO Token validation failed: No token provided');
            return false;
        }

        $isValid = hash_equals($this->ssoSharedSecret, $providedToken);
        
        if (!$isValid) {
            $this->logger->warning('SSO Token validation failed: Invalid token provided', [
                'provided_token_length' => strlen($providedToken),
                'expected_token_length' => strlen($this->ssoSharedSecret)
            ]);
        } else {
            $this->logger->debug('SSO Token validation successful');
        }

        return $isValid;
    }

    /**
     * Récupère la clé secrète SSO (pour les tests uniquement)
     * 
     * @return string La clé secrète SSO
     */
    public function getSsoSharedSecret(): string
    {
        return $this->ssoSharedSecret;
    }
} 
