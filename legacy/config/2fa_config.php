<?php

/**
 * Configuration pour l'intégration 2FA avec Symfony
 * 
 * Ce fichier centralise tous les paramètres nécessaires pour l'intégration
 * entre l'application legacy et Symfony.
 */

class TwoFactorConfig
{
    /**
     * Clé secrète partagée entre le legacy et Symfony pour l'authentification SSO
     * Doit correspondre à la variable SSO_SHARED_SECRET dans le .env de Symfony
     */
    public static function getSsoSharedSecret(): string
    {
        return $_ENV['SSO_SHARED_SECRET'] ?? 'CHANGE_ME_IN_PRODUCTION_a_super_secret_key_12345';
    }

    /**
     * URL de base de l'application Symfony
     * En développement : http://apache:80
     * En production : https://votre-domaine.com
     */
    public static function getSymfonyBaseUrl(): string
    {
        return $_ENV['SYMFONY_BASE_URL'] ?? 'http://apache:80';
    }

    /**
     * URL complète pour l'endpoint SSO login
     */
    public static function getSsoLoginUrl(): string
    {
        return self::getSymfonyBaseUrl() . '/symfony/api/internal/sso/login';
    }

    /**
     * URL complète pour l'endpoint SSO logout
     */
    public static function getSsoLogoutUrl(): string
    {
        return self::getSymfonyBaseUrl() . '/symfony/api/internal/sso/logout';
    }

    /**
     * URL de la page de validation 2FA
     */
    public static function get2faLoginUrl(): string
    {
        return '/symfony/security/2fa/login';
    }

    /**
     * URL de la page de gestion 2FA
     */
    public static function get2faManagementUrl(): string
    {
        return '/symfony/account/2fa';
    }

    /**
     * Timeout pour les appels HTTP vers Symfony (en secondes)
     */
    public static function getHttpTimeout(): int
    {
        return (int) ($_ENV['SYMFONY_HTTP_TIMEOUT'] ?? 5);
    }

    /**
     * Vérifie si l'intégration 2FA est activée
     */
    public static function isEnabled(): bool
    {
        return filter_var($_ENV['TWO_FACTOR_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Mode de debug pour les logs détaillés
     */
    public static function isDebugMode(): bool
    {
        return filter_var($_ENV['TWO_FACTOR_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }
} 
