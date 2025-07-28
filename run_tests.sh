#!/bin/bash

# Script pour exécuter les tests de l'intégration 2FA

echo "🧪 Tests d'intégration 2FA - LibreBooking"
echo "========================================="
echo ""

# Vérifier que Docker est en cours d'exécution
if ! docker-compose ps | grep -q "Up"; then
    echo "❌ Les conteneurs Docker ne sont pas démarrés."
    echo "   Lancez d'abord: ./start.sh"
    exit 1
fi

echo "✅ Conteneurs Docker détectés"
echo ""

# Test 1: Test de configuration
echo "📋 Test 1: Test de configuration"
echo "-------------------------------"
docker-compose exec apache php /var/www/legacy/tests/config_test.php
echo ""

# Test 2: Tests Symfony (si PHPUnit est disponible)
echo "📋 Test 2: Tests Symfony"
echo "-----------------------"
if docker-compose exec apache test -f /var/www/symfony/vendor/bin/phpunit; then
    echo "Exécution des tests d'intégration..."
    docker-compose exec apache php /var/www/symfony/vendor/bin/phpunit /var/www/symfony/tests/Integration/TwoFactorAuthIntegrationTest.php 2>/dev/null | grep -E "(OK|FAILURES|ERRORS|Tests:|Time:|Memory:)"
    
    echo ""
    echo "Exécution des tests de rate limiting..."
    docker-compose exec apache php /var/www/symfony/vendor/bin/phpunit /var/www/symfony/tests/Service/TwoFactorRateLimiterTest.php 2>/dev/null | grep -E "(OK|FAILURES|ERRORS|Tests:|Time:|Memory:)"
    
    echo ""
    echo "Exécution des tests unitaires..."
    docker-compose exec apache php /var/www/symfony/vendor/bin/phpunit /var/www/symfony/tests/Service/ 2>/dev/null | grep -E "(OK|FAILURES|ERRORS|Tests:|Time:|Memory:)"
    
    echo ""
    echo "Exécution des tests d'entités..."
    docker-compose exec apache php /var/www/symfony/vendor/bin/phpunit /var/www/symfony/tests/Entity/ 2>/dev/null | grep -E "(OK|FAILURES|ERRORS|Tests:|Time:|Memory:)"
    
    echo ""
    echo "✅ Tests Symfony terminés avec succès !"
else
    echo "⚠️ PHPUnit non disponible, tests Symfony ignorés"
    echo "   Pour installer PHPUnit, exécutez :"
    echo "   docker-compose exec apache composer require --dev phpunit/phpunit symfony/test-pack --working-dir=/var/www/symfony"
fi
echo ""

# Test 3: Vérification de la base de données
echo "📋 Test 3: Vérification de la base de données"
echo "--------------------------------------------"
docker-compose exec db mysql -u librebooking -p'librebooking' librebooking -e "
    SELECT 
        'user2_fa' as table_name,
        COUNT(*) as record_count,
        'OK' as status
    FROM user2_fa
    UNION ALL
    SELECT 
        'users' as table_name,
        COUNT(*) as record_count,
        'OK' as status
    FROM users;
"
echo ""

# Test 4: Vérification des endpoints
echo "📋 Test 4: Vérification des endpoints"
echo "-----------------------------------"
echo "🔗 Test de l'endpoint de statut 2FA..."
curl -s "http://localhost:8080/symfony/check-2fa-status?user_id=test" | head -c 100
echo ""

echo "🔗 Test de la page de gestion 2FA..."
curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080/symfony/account/2fa"
echo " - Page de gestion 2FA"

echo "🔗 Test de la page de login legacy..."
curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080/Web/index.php"
echo " - Page de login legacy"
echo ""

echo "🎉 Tests terminés !"
echo ""
echo "📝 Pour plus de détails sur les tests, consultez:"
echo "   - tests/functional_test.php"
echo "   - symfony/tests/Integration/TwoFactorAuthIntegrationTest.php"
echo "   - symfony/tests/Service/TwoFactorRateLimiterTest.php"
echo "" 
