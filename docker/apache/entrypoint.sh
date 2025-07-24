#!/bin/bash

# Démarre Apache en avant-plan pour que le conteneur reste actif.
# C'est la seule chose que ce script doit faire.
# L'orchestration (installation, migrations) est gérée par le script start.sh à l'extérieur du conteneur.
echo "Démarrage d'Apache..."
apache2-foreground 
