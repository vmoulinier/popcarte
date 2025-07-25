<?php

/**
 * Test simple de validation de la configuration 2FA
 * 
 * Ce test vérifie que la configuration est correctement chargée
 * et que les variables d'environnement sont accessibles.
 */

require_once __DIR__ . '/../config/2fa_config.php';

echo "🧪 Test de validation de la configuration 2FA\n";
echo "============================================\n\n";

// Test 1: Vérification des variables d'environnement
echo "📋 Test 1: Variables d'environnement\n";
echo "-----------------------------------\n";

$tests = [
    'SSO_SHARED_SECRET' => TwoFactorConfig::getSsoSharedSecret(),
    'SYMFONY_BASE_URL' => TwoFactorConfig::getSymfonyBaseUrl(),
    'SYMFONY_HTTP_TIMEOUT' => TwoFactorConfig::getHttpTimeout(),
    'TWO_FACTOR_ENABLED' => TwoFactorConfig::isEnabled() ? 'true' : 'false',
    'TWO_FACTOR_DEBUG' => TwoFactorConfig::isDebugMode() ? 'true' : 'false'
];

foreach ($tests as $name => $value) {
    $status = !empty($value) ? '✅' : '❌';
    echo "{$status} {$name}: {$value}\n";
}

echo "\n";

// Test 2: Vérification des URLs générées
echo "📋 Test 2: URLs générées\n";
echo "------------------------\n";

$urls = [
    'SSO Login URL' => TwoFactorConfig::getSsoLoginUrl(),
    'SSO Logout URL' => TwoFactorConfig::getSsoLogoutUrl(),
    '2FA Management URL' => TwoFactorConfig::get2faManagementUrl(),
    '2FA Login URL' => TwoFactorConfig::get2faLoginUrl()
];

foreach ($urls as $name => $url) {
    $status = !empty($url) ? '✅' : '❌';
    echo "{$status} {$name}: {$url}\n";
}

echo "\n";

// Test 3: Validation de la configuration
echo "📋 Test 3: Validation de la configuration\n";
echo "----------------------------------------\n";

$errors = [];

if (empty(TwoFactorConfig::getSsoSharedSecret())) {
    $errors[] = "SSO_SHARED_SECRET est vide";
}

if (empty(TwoFactorConfig::getSymfonyBaseUrl())) {
    $errors[] = "SYMFONY_BASE_URL est vide";
}

if (TwoFactorConfig::getHttpTimeout() <= 0) {
    $errors[] = "SYMFONY_HTTP_TIMEOUT doit être positif";
}

if (count($errors) === 0) {
    echo "✅ Configuration valide\n";
} else {
    echo "❌ Erreurs de configuration:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
}

echo "\n";

// Test 4: Test de la base de données
echo "📋 Test 4: Test de la base de données\n";
echo "------------------------------------\n";

try {
    // Utiliser l'hôte Docker interne
    $pdo = new PDO(
        'mysql:host=db;port=3306;dbname=librebooking',
        'librebooking',
        'librebooking'
    );
    
    // Test de connexion
    $stmt = $pdo->query("SELECT 1");
    if ($stmt->fetch()) {
        echo "✅ Connexion à la base de données réussie\n";
    }
    
    // Test de la table user2_fa
    $stmt = $pdo->query("SHOW TABLES LIKE 'user2_fa'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table user2_fa existe\n";
        
        // Compter les utilisateurs 2FA
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM user2_fa");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 {$result['count']} utilisateurs 2FA enregistrés\n";
    } else {
        echo "❌ Table user2_fa n'existe pas\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur base de données: " . $e->getMessage() . "\n";
}

echo "\n";

// Résumé
echo "🎉 Test de configuration terminé !\n";
echo "================================\n";
echo "La configuration 2FA est " . (count($errors) === 0 ? "✅ valide" : "❌ invalide") . "\n"; 
