<?php

/**
 * Chargeur de variables d'environnement pour l'application legacy
 * 
 * Cette classe charge les variables d'environnement depuis un fichier .env
 * et les rend disponibles via $_ENV
 */
class EnvironmentLoader
{
    /**
     * Charge les variables d'environnement depuis le fichier .env
     * 
     * @param string $envPath Chemin vers le fichier .env (optionnel)
     * @return void
     */
    public static function load($envPath = null)
    {
        if ($envPath === null) {
            $envPath = ROOT_DIR . '.env';
        }

        if (!file_exists($envPath)) {
            // Si le fichier .env n'existe pas, on utilise les valeurs par défaut
            self::setDefaultValues();
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Ignorer les lignes vides
            if (empty(trim($line))) {
                continue;
            }

            // Parser les variables d'environnement
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Supprimer les guillemets si présents
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                $_ENV[$key] = $value;
            }
        }

        // S'assurer que les valeurs par défaut sont définies
        self::setDefaultValues();
    }

    /**
     * Définit les valeurs par défaut pour les variables d'environnement
     * 
     * @return void
     */
    private static function setDefaultValues()
    {
        $defaults = [
            'SSO_SHARED_SECRET' => 'CHANGE_ME_IN_PRODUCTION_a_super_secret_key_12345',
            'SYMFONY_BASE_URL' => 'http://apache:80',
            'SYMFONY_HTTP_TIMEOUT' => '5',
            'TWO_FACTOR_ENABLED' => 'true',
            'TWO_FACTOR_DEBUG' => 'false'
        ];

        foreach ($defaults as $key => $defaultValue) {
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $defaultValue;
            }
        }
    }
} 
