# LibreBooking avec 2FA - Strangler Pattern

Ce projet implémente un système d'authentification à deux facteurs (2FA) pour une application PHP legacy (LibreBooking) en utilisant le **Strangler Pattern**. Une nouvelle application Symfony gère tout le processus 2FA, s'intégrant de manière transparente dans le flux de connexion existant.

## 🚀 Démarrage rapide

1.  **Prérequis**: Docker et Docker Compose.
2.  **Rendre le script exécutable (si nécessaire)** :
    Sur macOS ou Linux, il se peut que vous deviez donner la permission d'exécution au script de démarrage. Exécutez cette commande une seule fois :
    ```bash
    chmod +x start.sh
    ```
3.  **Lancement**:
    ```bash
    ./start.sh
    ```
    Ce script construit les conteneurs, installe les dépendances Composer et exécute les migrations de base de données.

4.  **Finaliser l'installation Legacy** :
    Une fois le script terminé, ouvrez votre navigateur et allez à l'adresse suivante pour lancer l'installateur web de LibreBooking :
    **http://localhost:8080/Web/install/**
    -   Lorsqu'il vous est demandé un mot de passe d'installation, entrez : `popcarte`
    -   Sur la page de configuration, cochez les trois cases suivantes :
        -   `Créer la base de données (librebooking)Attention: cela va effacer toutes les données existantes`
        -   `Créer le compte utilisateur de la base (librebooking)`
        -   `Importer des exemples de données. Cela va créer le compte administrateur: admin/popcarte et le compte utilisateur: user/popcarte`
    -   Suivez les étapes restantes pour finaliser la configuration.

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
│   ├── Presenters/LoginPresenter.php  # Point d'intégration 2FA au login
│   └── Web/                   # Interface utilisateur
├── symfony/                   # Nouvelle couche 2FA
│   ├── src/Controller/        # Contrôleurs 2FA
│   │   ├── Account2FAController.php    # Activation/désactivation 2FA
│   │   ├── Security2FAController.php   # Validation 2FA à la connexion
│   │   ├── SsoController.php # Login/Logout Legacy <-> Symfony
│   │   ├── TwoFactorAuthController.php # Vérification d'état 2FA
│   ├── src/Entity/           # Entités Doctrine
│   └── templates/            # Interface 2FA
│       ├── account/2fa.html.twig       # Page d'activation
│       └── security/2fa_login.html.twig # Page de validation
├── docker/                   # Configuration Docker
└── docker-compose.yml        # Services Docker
```

## 🔧 Développement

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

## 🔒 Note sur la sécurité des requêtes SQL

Le framework PHP legacy utilisé dans ce projet a une particularité : sa classe `AdHocCommand` ne supporte pas les requêtes préparées (avec les `?`), ce qui peut ouvrir la porte à des **injections SQL** si des précautions ne sont pas prises.

Lors de la validation du `login_token` dans `LoginPresenter`, une requête SQL est construite manuellement. Pour sécuriser cette opération :

1.  Une nouvelle méthode publique, `EscapeString(string $string)`, a été ajoutée à la classe de connexion à la base de données (`legacy/lib/Database/MySQL/MySqlConnection.php`).
2.  Cette méthode utilise `mysqli_real_escape_string()`, la fonction PHP standard et sécurisée pour échapper tous les caractères spéciaux d'une chaîne avant de l'insérer dans une requête.
3.  Toutes les données variables insérées dans des `AdHocCommand` **doivent impérativement** être passées à travers cette méthode pour garantir la sécurité de l'application.

Cette approche permet de se prémunir contre les injections SQL tout en s'adaptant aux contraintes du framework legacy.

--- 
