#!/bin/bash

# Créer le dossier tpl_c avec les bonnes permissions pour Smarty
echo "Configuration des permissions pour Smarty..."
mkdir -p /var/www/legacy/tpl_c
chown -R www-data:www-data /var/www/legacy/tpl_c
chmod -R 755 /var/www/legacy/tpl_c

# Démarre Apache en avant-plan pour que le conteneur reste actif.
# C'est la seule chose que ce script doit faire.
# L'orchestration (installation, migrations) est gérée par le script start.sh à l'extérieur du conteneur.
echo "Démarrage d'Apache..."
apache2-foreground 
