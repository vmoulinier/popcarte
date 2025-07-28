<?php

/**
 * Test fonctionnel pour l'intégration 2FA
 * 
 * Ce script teste manuellement le flux complet d'authentification 2FA
 * entre l'application legacy et Symfony.
 * 
 * Usage: php tests/functional_test.php
 */

require_once __DIR__ . '/../config/2fa_config.php';

class TwoFactorFunctionalTest
{
    private $baseUrl = 'http://localhost:8080';
    private $testResults = [];

    public function runAllTests()
    {
        echo "🧪 Démarrage des tests fonctionnels 2FA\n";
        echo "=====================================\n\n";

        $this->testConfigurationLoading();
        $this->testSymfonyEndpoints();
        $this->testLegacyIntegration();
        $this->testDatabaseOperations();

        $this->displayResults();
    }

    private function testConfigurationLoading()
    {
        echo "📋 Test 1: Chargement de la configuration\n";
        
        try {
            $ssoSecret = TwoFactorConfig::getSsoSharedSecret();
            $symfonyUrl = TwoFactorConfig::getSymfonyBaseUrl();
            $timeout = TwoFactorConfig::getHttpTimeout();
            
            $this->testResults['config'] = [
                'success' => true,
                'message' => "Configuration chargée: SSO_URL={$symfonyUrl}, TIMEOUT={$timeout}s"
            ];
            echo "✅ Configuration chargée avec succès\n";
        } catch (Exception $e) {
            $this->testResults['config'] = [
                'success' => false,
                'message' => "Erreur: " . $e->getMessage()
            ];
            echo "❌ Erreur de configuration: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    private function testSymfonyEndpoints()
    {
        echo "🔗 Test 2: Endpoints Symfony\n";
        
        // Test de l'endpoint de statut 2FA
        $statusUrl = $this->baseUrl . '/symfony/check-2fa-status?user_id=test_user';
        $response = $this->makeHttpRequest($statusUrl);
        
        if ($response && isset($response['has_2fa'])) {
            $this->testResults['symfony_status'] = [
                'success' => true,
                'message' => "Endpoint de statut 2FA accessible"
            ];
            echo "✅ Endpoint de statut 2FA accessible\n";
        } else {
            $this->testResults['symfony_status'] = [
                'success' => false,
                'message' => "Endpoint de statut 2FA inaccessible"
            ];
            echo "❌ Endpoint de statut 2FA inaccessible\n";
        }

        // Test de l'endpoint SSO
        $ssoUrl = $this->baseUrl . '/symfony/api/internal/sso/login';
        $headers = [
            'X-SSO-TOKEN: ' . TwoFactorConfig::getSsoSharedSecret(),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $response = $this->makeHttpRequest($ssoUrl, 'POST', 'user_id=test_user', $headers);
        
        if ($response !== false) {
            $this->testResults['symfony_sso'] = [
                'success' => true,
                'message' => "Endpoint SSO accessible"
            ];
            echo "✅ Endpoint SSO accessible\n";
        } else {
            $this->testResults['symfony_sso'] = [
                'success' => false,
                'message' => "Endpoint SSO inaccessible"
            ];
            echo "❌ Endpoint SSO inaccessible\n";
        }
        echo "\n";
    }

    private function testLegacyIntegration()
    {
        echo "🔄 Test 3: Intégration Legacy\n";
        
        // Test de la page de login legacy
        $loginUrl = $this->baseUrl . '/Web/index.php';
        $response = $this->makeHttpRequest($loginUrl);
        
        if ($response !== false) {
            $this->testResults['legacy_login'] = [
                'success' => true,
                'message' => "Page de login legacy accessible"
            ];
            echo "✅ Page de login legacy accessible\n";
        } else {
            $this->testResults['legacy_login'] = [
                'success' => false,
                'message' => "Page de login legacy inaccessible"
            ];
            echo "❌ Page de login legacy inaccessible\n";
        }

        // Test de la page de gestion 2FA
        $managementUrl = $this->baseUrl . '/symfony/account/2fa';
        $response = $this->makeHttpRequest($managementUrl);
        
        if ($response !== false) {
            $this->testResults['legacy_2fa_management'] = [
                'success' => true,
                'message' => "Page de gestion 2FA accessible"
            ];
            echo "✅ Page de gestion 2FA accessible\n";
        } else {
            $this->testResults['legacy_2fa_management'] = [
                'success' => false,
                'message' => "Page de gestion 2FA inaccessible"
            ];
            echo "❌ Page de gestion 2FA inaccessible\n";
        }
        echo "\n";
    }

    private function testDatabaseOperations()
    {
        echo "🗄️ Test 4: Opérations Base de données\n";
        
        try {
            // Test de connexion à la base de données
            $pdo = new PDO(
                'mysql:host=localhost;port=3306;dbname=librebooking',
                'librebooking',
                'librebooking'
            );
            
            // Test de lecture de la table user2_fa
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM user2_fa");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->testResults['database'] = [
                'success' => true,
                'message' => "Base de données accessible, {$result['count']} utilisateurs 2FA"
            ];
            echo "✅ Base de données accessible\n";
            echo "📊 {$result['count']} utilisateurs 2FA enregistrés\n";
            
        } catch (Exception $e) {
            $this->testResults['database'] = [
                'success' => false,
                'message' => "Erreur base de données: " . $e->getMessage()
            ];
            echo "❌ Erreur base de données: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    private function makeHttpRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $data,
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }

        // Essayer de décoder JSON
        $jsonResponse = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonResponse;
        }

        return $response;
    }

    private function displayResults()
    {
        echo "📊 Résultats des tests\n";
        echo "=====================\n\n";

        $successCount = 0;
        $totalCount = count($this->testResults);

        foreach ($this->testResults as $testName => $result) {
            $status = $result['success'] ? '✅' : '❌';
            echo "{$status} {$testName}: {$result['message']}\n";
            
            if ($result['success']) {
                $successCount++;
            }
        }

        echo "\n";
        echo "📈 Résumé: {$successCount}/{$totalCount} tests réussis\n";
        
        if ($successCount === $totalCount) {
            echo "🎉 Tous les tests sont passés avec succès !\n";
        } else {
            echo "⚠️ Certains tests ont échoué. Vérifiez la configuration.\n";
        }
    }
}

// Exécuter les tests si le script est appelé directement
if (php_sapi_name() === 'cli') {
    $test = new TwoFactorFunctionalTest();
    $test->runAllTests();
} 
