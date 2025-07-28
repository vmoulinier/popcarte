# LibreBooking avec 2FA - Strangler Pattern

Ce projet implémente un système d'authentification à deux facteurs (2FA) pour une application PHP legacy (LibreBooking) en utilisant le **Strangler Pattern**. Une nouvelle application Symfony gère tout le processus 2FA, s'intégrant de manière transparente dans le flux de connexion existant.

## 🚀 Démarrage rapide

1.  **Prérequis**: Docker et Docker Compose.
2.  **Rendre le script exécutable (si nécessaire)** :
    Sur macOS ou Linux, il se peut que vous deviez donner la permission d'exécution au script de démarrage. Exécutez cette commande une seule fois :
    ```bash
    chmod +x start.sh
    ```
3.  **Configuration Legacy** :
    Avant de lancer l'application, vous devez copier et renommer le fichier de configuration :
    ```bash
    cp legacy/config/config.dist.php legacy/config/config.php
    ```
4.  **Lancement**:
    ```bash
    ./start.sh
    ```
    Ce script construit les conteneurs, installe les dépendances Composer et exécute les migrations de base de données.

5.  **Finaliser l'installation Legacy** :
    Une fois le script terminé, ouvrez votre navigateur et allez à l'adresse suivante pour lancer l'installateur web de LibreBooking :
    **http://localhost:8080/Web/install/**
    -   Lorsqu'il vous est demandé un mot de passe d'installation, entrez : `popcarte`
    -   Rentrez en Nom d'utilisateur MySQL et Mot de Passe `root` et `root`
    -   Sur la page de configuration, cochez les trois cases suivantes :
        -   `Créer la base de données (librebooking)Attention: cela va effacer toutes les données existantes`
        -   `Créer le compte utilisateur de la base (librebooking)`
        -   `Importer des exemples de données. Cela va créer le compte administrateur: admin/popcarte et le compte utilisateur: user/popcarte`
    -   Suivez les étapes restantes pour finaliser la configuration.

6.  **Exécuter les migrations Symfony** :
    Après l'installation de LibreBooking, vous devez exécuter les migrations Symfony pour créer la table 2FA :
    ```bash
    docker-compose exec apache php /var/www/symfony/bin/console doctrine:migrations:migrate --no-interaction
    ```

## 🌐 URLs d'accès (après installation)

-   **Application Legacy**: http://localhost:8080/Web/index.php
-   **Gestion 2FA**: http://localhost:8080/symfony/account/2fa
-   **Base de données (PhpMyAdmin)**: http://localhost:8081

### Identifiants de connexion
Pour les tests, utilisez les identifiants suivants :
-   **Utilisateur**: `user`
-   **Mot de passe**: `popcarte`

---

## 🔐 Flux d'authentification 2FA

Le processus de connexion est orchestré entre l'application legacy et la nouvelle application Symfony.

### Étape 1 : Connexion Legacy
L'utilisateur se connecte avec son nom d'utilisateur et son mot de passe sur l'interface legacy.

### Étape 2 : Création de la session Symfony via SSO  
Une fois les identifiants vérifiés, le `LoginPresenter` appelle l'endpoint Symfony `/symfony/api/internal/sso/login` à l'aide d'une requête POST sécurisée (en-tête `X-SSO-TOKEN`).  
Symfony authentifie l'utilisateur, crée la session et renvoie un en-tête `Set-Cookie` contenant le cookie de session.  
Le legacy relaie immédiatement cet en-tête au navigateur, ce qui permet au client de disposer de la session Symfony pour les requêtes suivantes.

### Étape 3 : Scénarios de redirection
-   **2FA non configurée ou désactivée** : L'utilisateur est connecté immédiatement au legacy. Il pourra ensuite activer la 2FA depuis le menu « Mon Compte » (lien `/symfony/account/2fa`).
-   **2FA déjà activée** : L'utilisateur est redirigé vers la page de validation de Symfony (`/symfony/security/2fa/login`) où il doit entrer le code TOTP actuel de son application d'authentification.

> **Note :** Une fois connecté, l'utilisateur peut à tout moment gérer ses paramètres de double authentification (activer ou désactiver) en se rendant dans le menu "Mon Compte" → "Gérer ma 2FA".

### Étape 4 : Finalisation de la connexion via un jeton
-   Après une validation 2FA réussie sur Symfony (que ce soit pour une activation ou une connexion), Symfony ne redirige pas directement. Il affiche une page intermédiaire qui **soumet automatiquement un formulaire en POST** vers la page d'accueil legacy (`/Web/index.php`).
-   Ce formulaire contient un **jeton de connexion (`login_token`) sécurisé, à usage unique et à courte durée de vie**, qui a été stocké dans la table `user2_fa`.
-   L'application legacy (`LoginPresenter`) reçoit ce jeton, le valide en base de données, puis crée manuellement la session de l'utilisateur et le redirige vers le tableau de bord. Le jeton est ensuite invalidé.

Ce mécanisme de jeton POST assure une transition sécurisée et fiable entre les deux applications sans exposer de données sensibles.

## 📁 Fichiers clés de l'intégration

-   `legacy/Presenters/LoginPresenter.php`:
    -   Contient la logique de vérification du statut 2FA après la validation du mot de passe.
    -   Contient les redirections vers Symfony.
    -   `LoginWithToken()`: Gère la connexion via le jeton POST reçu de Symfony, crée la session legacy.
-   `legacy/Web/install/`:
    -   Contient les scripts et templates pour l'installation initiale de l'application legacy.
-   `legacy/Web/index.php`:
    -   Modifié pour détecter la présence d'un `login_token` et déclencher la logique de connexion par jeton.
-   `legacy/tpl/globalheader.tpl`:
    -   Contient le lien vers la page de gestion 2FA dans le menu "Mon Compte".
-   `symfony/src/Controller/`:
    -   `Account2FAController.php`: Gère la page d'activation (QR code) et de désactivation de la 2FA. Génère le jeton après activation.
    -   `Security2FAController.php`: Gère la page de validation du code TOTP pour les connexions. Génère le jeton après validation.
    -   `TwoFactorAuthController.php`: API interne pour connaître l'état 2FA d'un utilisateur.
    -   `SsoController.php`: Point d'entrée SSO (login/logout) appelé depuis le legacy pour créer ou détruire la session Symfony.
-   `symfony/src/Entity/User2FA.php`:
    -   Entité Doctrine pour la table `user2_fa`, contient les champs pour le secret 2FA et le jeton de connexion temporaire.
-   `symfony/templates/security/auto_post_redirect.html.twig`:
    -   Le template qui contient le formulaire auto-soumis pour la transition de Symfony vers le legacy.

---

## 🛠️ Configuration automatique

Le système se configure automatiquement au démarrage :
- ✅ Installation des dépendances Symfony
- ✅ Exécution des migrations de base de données
- ✅ Vidage du cache
- ✅ Démarrage d'Apache

## 📁 Structure du projet

```
popcarte/
├── legacy/                    # LibreBooking existant
│   ├── config/
│   │   └── 2fa_config.php    # Configuration centralisée 2FA
│   ├── lib/Config/
│   │   └── EnvironmentLoader.php  # Chargeur variables d'environnement
│   ├── Presenters/LoginPresenter.php  # Point d'intégration 2FA au login
│   ├── tests/
│   │   └── config_test.php   # Test fonctionnel de configuration
│   └── Web/                   # Interface utilisateur
├── symfony/                   # Nouvelle couche 2FA
│   ├── config/services/
│   │   └── 2fa.yaml          # Configuration services 2FA
│   ├── src/Controller/        # Contrôleurs 2FA
│   │   ├── Account2FAController.php    # Activation/désactivation 2FA
│   │   ├── Security2FAController.php   # Validation 2FA à la connexion
│   │   ├── SsoController.php # Login/Logout Legacy <-> Symfony
│   │   ├── TwoFactorAuthController.php # Vérification d'état 2FA
│   │   └── SsoController.php  # API SSO interne
│   ├── src/Service/           # Services Symfony
│   │   ├── TwoFactorConfigService.php  # Configuration 2FA
│   │   └── SsoTokenValidator.php       # Validation tokens SSO
│   ├── src/Entity/           # Entités Doctrine
│   ├── tests/                # Tests PHPUnit
│   │   ├── Service/          # Tests unitaires services
│   │   ├── Entity/           # Tests unitaires entités
│   │   └── Integration/      # Tests d'intégration
│   └── templates/            # Interface 2FA
│       ├── account/2fa.html.twig       # Page d'activation
│       └── security/2fa_login.html.twig # Page de validation
├── docker/                   # Configuration Docker
├── docker-compose.yml        # Services Docker
└── run_tests.sh              # Script d'exécution des tests
```

## 🔧 Développement

### Configuration
Le projet utilise des variables d'environnement pour la configuration. Copiez le fichier d'exemple :
```bash
cp legacy/env.example legacy/.env
```

Variables disponibles :
- `SSO_SHARED_SECRET` : Clé secrète partagée entre legacy et Symfony
- `SYMFONY_BASE_URL` : URL de base de l'application Symfony
- `SYMFONY_HTTP_TIMEOUT` : Timeout pour les appels HTTP (secondes)
- `TWO_FACTOR_ENABLED` : Activer/désactiver l'intégration 2FA (true/false)
- `TWO_FACTOR_DEBUG` : Mode debug pour les logs détaillés (true/false)

**Nouveaux fichiers de configuration :**
- `legacy/config/2fa_config.php` : Classe centralisée pour la configuration 2FA
- `legacy/lib/Config/EnvironmentLoader.php` : Chargeur automatique des variables d'environnement
- `symfony/config/services/2fa.yaml` : Configuration Symfony pour les services 2FA
- `symfony/src/Service/TwoFactorConfigService.php` : Service Symfony pour la configuration
- `symfony/src/Service/SsoTokenValidator.php` : Service de validation sécurisée des tokens SSO

### Tests
Exécutez tous les tests d'intégration :
```bash
./run_tests.sh
```

Ou lancez un test spécifique :
```bash
# Test de configuration PHP
docker-compose exec apache php /var/www/legacy/tests/config_test.php

# Tests Symfony unitaires
docker-compose exec apache php /var/www/symfony/vendor/bin/phpunit /var/www/symfony/tests/Service/
docker-compose exec apache php /var/www/symfony/vendor/bin/phpunit /var/www/symfony/tests/Entity/

# Tests Symfony d'intégration
docker-compose exec apache php /var/www/symfony/vendor/bin/phpunit /var/www/symfony/tests/Integration/
```

**Tests disponibles :**
- **Tests unitaires** : 18 tests pour les services Symfony
- **Tests d'entités** : 12 tests pour les entités Doctrine
- **Tests d'intégration** : 5 tests pour le flux 2FA complet
- **Test fonctionnel** : Validation de la configuration et de la base de données

### Logs en temps réel
```bash
docker-compose logs -f
```

### Reconstruire les images
```bash
docker-compose build --no-cache
```

### Accès à la base de données
```bash
docker-compose exec db mysql -u librebooking -p librebooking
```

## 🧪 Tests

### Résultats des tests
Le projet inclut une suite de tests complète :
- ✅ **35/35 tests PHPUnit passent**
- ✅ **Test fonctionnel de configuration** : Validation de la base de données et des endpoints
- ✅ **Tests unitaires** : Services et entités Symfony
- ✅ **Tests d'intégration** : Flux 2FA complet

Exécutez `./run_tests.sh` pour voir tous les résultats.

### Test 1 : Utilisateur sans 2FA
1. Se connecter avec un utilisateur sans 2FA.
2. L'utilisateur est connecté directement au legacy.
3. Depuis le menu "Mon Compte", cliquer sur "Gérer ma 2FA" pour accéder à `/symfony/account/2fa`.
4. Activer la 2FA en scannant le QR code et en validant avec un code TOTP. Un jeton est généré puis l'utilisateur est automatiquement reconnecté.

### Test 2 : Utilisateur avec 2FA activée
1. Se connecter avec un utilisateur ayant déjà la 2FA activée.
2. Être redirigé vers la page de validation 2FA de Symfony (`/symfony/security/2fa/login`).
3. Saisir le code TOTP correct.
4. Être redirigé vers le tableau de bord, maintenant connecté.

### Test 3 : Code TOTP incorrect
1. Tenter de se connecter avec un code TOTP incorrect.
2. Un message d'erreur doit s'afficher sur la page de validation, permettant de réessayer.

### Test 4 : Désactivation 2FA
1. Se connecter avec un utilisateur ayant la 2FA activée.
2. Aller sur la page de gestion (`/symfony/account/2fa?user_id=[username]`) via le menu "Mon Compte".
3. Cliquer sur "Désactiver la 2FA".
4. La 2FA est maintenant désactivée. Lors de la prochaine connexion, l'utilisateur n'aura plus besoin de code.

### Test 5 : Rate Limiting
1. Se connecter avec un utilisateur ayant la 2FA activée.
2. Sur la page de validation 2FA, entrer 6 codes incorrects consécutifs.
3. Le système bloque temporairement l'accès avec le message "Trop de tentatives échouées".
4. Attendre 1 minute ou se reconnecter avec un code correct pour débloquer. 

## 🔒 Sécurité

### Rate Limiting 2FA
Le système inclut un **rate limiting intelligent** pour protéger contre les attaques par force brute :

- **Limite par utilisateur** : 5 tentatives échouées par 1 minute
- **Limite par IP** : 10 tentatives échouées par 1 minute
- **Reset automatique** : Le compteur se remet à zéro après une tentative réussie
- **Messages informatifs** : L'utilisateur voit le nombre de tentatives restantes

Le rate limiting utilise le **composant natif Symfony Rate Limiter** avec une politique de fenêtre fixe pour une performance optimale.

### Variables d'environnement
⚠️ **IMPORTANT** : Modifiez les valeurs par défaut dans `legacy/.env` pour la production :
- `SSO_SHARED_SECRET` : Utilisez une clé secrète forte et unique
- `SYMFONY_BASE_URL` : URL de production sécurisée (HTTPS)
- `TWO_FACTOR_DEBUG` : Désactivez en production (`false`)
- `TWO_FACTOR_ENABLED` : Activez en production (`true`)

### Protection contre les injections SQL
Le framework PHP legacy utilisé dans ce projet a une particularité : sa classe `AdHocCommand` ne supporte pas les requêtes préparées (avec les `?`), ce qui peut ouvrir la porte à des **injections SQL** si des précautions ne sont pas prises.

Lors de la validation du `login_token` dans `LoginPresenter`, une requête SQL est construite manuellement. Pour sécuriser cette opération :

1.  Une nouvelle méthode publique, `EscapeString(string $string)`, a été ajoutée à la classe de connexion à la base de données (`legacy/lib/Database/MySQL/MySqlConnection.php`).
2.  Cette méthode utilise `mysqli_real_escape_string()`, la fonction PHP standard et sécurisée pour échapper tous les caractères spéciaux d'une chaîne avant de l'insérer dans une requête.
3.  Toutes les données variables insérées dans des `AdHocCommand` **doivent impérativement** être passées à travers cette méthode pour garantir la sécurité de l'application.

Cette approche permet de se prémunir contre les injections SQL tout en s'adaptant aux contraintes du framework legacy.

### Gestion d'erreurs
Le système inclut une gestion d'erreurs robuste :
- **Fallback automatique** : Si Symfony est indisponible, le legacy fonctionne sans 2FA
- **Timeouts configurables** : Évite les blocages lors des appels HTTP
- **Logs détaillés** : Traçabilité complète des opérations 2FA

### Tests de sécurité
Les tests incluent des vérifications de sécurité :
- Validation des tokens SSO avec `hash_equals()` pour éviter les attaques temporelles
- Protection contre les requêtes non authentifiées
- Tests de résistance aux injections SQL
- Validation des variables d'environnement
- Tests de configuration sécurisée

### Améliorations apportées
- **Configuration centralisée** : Variables d'environnement dans `.env` au lieu de valeurs en dur
- **Services Symfony** : Architecture modulaire avec injection de dépendances
- **Tests complets** : 35 tests PHPUnit couvrant unitaires, entités et intégration
- **Sécurité renforcée** : Validation sécurisée des tokens avec `hash_equals()`
- **Debug désactivé** : Configuration propre pour la production
- **Documentation complète** : Guide d'installation et d'utilisation détaillé

---
