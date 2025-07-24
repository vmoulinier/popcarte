#!/bin/bash

# Fonction pour afficher les messages de section
print_header() {
    echo ""
    echo "================================================================="
    echo "    $1"
    echo "================================================================="
    echo ""
}

# Arrêter tous les conteneurs en cours pour éviter les conflits
print_header "📦 Arrêt des conteneurs existants..."
docker-compose down --remove-orphans

# Reconstruire les images pour s'assurer qu'elles sont à jour
print_header "🔨 Construction des images Docker..."
docker-compose build

# Démarrer les services en arrière-plan
print_header "▶️  Démarrage des services (PHP, Apache, MySQL)..."
docker-compose up -d

# Attendre que le conteneur de base de données soit pleinement opérationnel
print_header "⏳ Attente de la base de données..."
while ! docker-compose exec -T db mysqladmin ping -h"localhost" -u"root" -p"root" --silent; do
    echo "   En attente de la connexion à la base de données..."
    sleep 2
done
echo "✅ Base de données prête !"

# Installer les dépendances de l'application Legacy
print_header "🚚 Installation des dépendances pour l'application Legacy..."
docker-compose exec -T apache composer install --working-dir=/var/www/legacy --no-interaction --optimize-autoloader

# Installer les dépendances de l'application Symfony
print_header "🚚 Installation des dépendances pour l'application Symfony..."
docker-compose exec -T apache composer install --working-dir=/var/www/symfony --no-interaction --optimize-autoloader

# Exécuter les migrations de la base de données pour Symfony
print_header "⚙️ Exécution des migrations Symfony..."
docker-compose exec -T apache php /var/www/symfony/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
echo "✅ Table 'user2_fa' créée ou mise à jour."

# Afficher les informations finales
print_header "🎉 Installation terminée !"
echo "L'environnement LibreBooking est prêt."
echo ""
echo "🌐 Application Legacy : http://localhost:8080/Web/index.php"
echo "🔐 Gestion 2FA :      http://localhost:8080/symfony/account/2fa?user_id=VOTRE_USER"
echo "🗄️  PhpMyAdmin :         http://localhost:8081"
echo ""
echo "Pour voir les logs en temps réel, lancez la commande :"
echo "docker-compose logs -f"
echo "" 
