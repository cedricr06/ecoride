# EcoRide — Plateforme de covoiturage (ECF DWWM)

> **But du dépôt** : fournir le code de l’application et **la démarche complète pour l’exécuter en local**, conformément aux livrables de l’énoncé.

## Sommaire
- [Contexte & objectifs](#contexte--objectifs)
- [Fonctionnalités clés](#fonctionnalités-clés)
- [Stack technique](#stack-technique)
- [Architecture du projet](#architecture-du-projet)
- [Prérequis](#prérequis)
- [Installation locale (pas à pas)](#installation-locale-pas-à-pas)
  - [1) Cloner & installer les dépendances](#1-cloner--installer-les-dépendances)
  - [2) Configuration `.env`](#2-configuration-env)
  - [3) Base de données SQL (MySQL/MariaDB)](#3-base-de-données-sql-mysqlmariadb)
  - [4) Base NoSQL (MongoDB)](#4-base-nosql-mongodb)
  - [5) Configuration Apache / Routage](#5-configuration-apache--routage)
  - [6) Lancer l’application en local](#6-lancer-lapplication-en-local)
- [Comptes de test & création de l’admin](#comptes-de-test--création-de-ladmin)
- [Qualité & outils dev](#qualité--outils-dev)
- [Sécurité (rappels de mise en œuvre)](#sécurité-rappels-de-mise-en-œuvre)
- [Dépannage (FAQ)](#dépannage-faq)
- [Kanban & documentation](#kanban--documentation)
- [Licence](#licence)

---

## Contexte & objectifs
EcoRide est une application web centrée sur le **covoiturage** avec un angle **éco‑responsable** (mise en valeur des véhicules électriques), développée dans le cadre de l’ECF DWWM. L’application couvre un parcours visiteur/utilisateur complet : consultation de trajets, filtres, détail d’un trajet, création de compte, participation avec crédits, gestion d’espace utilisateur (chauffeur/passager), saisie de voyages, démarrage/arrêt d’un covoiturage et **système d’avis** (validés côté employé), plus un **espace administrateur** (création d’employés, statistiques, suspension de comptes).

Le présent README décrit la **stack**, l’**architecture**, et surtout **toutes les étapes pour lancer le projet en local** (Windows/macOS/Linux), y compris la configuration Apache, les bases de données SQL & NoSQL et les variables d’environnement.

## Fonctionnalités clés
- Page d’accueil, menu global, recherche d’itinéraires (ville + date)
- Liste des trajets **filtrables** (écologique, prix max, durée max, note mini du conducteur)
- Fiche détail trajet (conducteur, véhicule, préférences, avis, horaires, prix, places)
- Création de compte (**mot de passe sécurisé**), attribution de **crédits de bienvenue**
- Participation à un trajet (double confirmation, décrément des places, débit de crédits)
- Espace utilisateur : rôle chauffeur/passager, **véhicules**, préférences, **saisie de voyage**
- Historique + **annulation** (mises à jour crédits/places, mails aux participants)
- **Démarrer / Arrivée à destination** → mails pour déposer un **avis** (modéré)
- Espace **employé** : validation/refus des avis, suivi des incidents
- Espace **admin** : gestion des employés, **graphiques** (trajets/jour, revenus crédits/jour), total crédits, suspension de comptes

> **Remarque** : la création du compte **administrateur** se fait **en amont** (voir plus bas).

## Stack technique
- **Front** : HTML5, CSS/Sass (Bootstrap 5), JavaScript (vanilla)
- **Back** : PHP 8.x (PDO), routeur « front‑controller » (`public/index.php`)
- **SQL** : MySQL/MariaDB (schéma relationnel) via PDO (requêtes préparées)
- **NoSQL** : MongoDB (ex. collections `avis`, `driver_stats`)
- **Mail** : PHPMailer (SMTP)
- **Outils** : Composer (PHP), Node/NPM (build Sass), Apache (mod_rewrite)

## Architecture du projet
```
.
├─ app/
│  ├─ Controllers/         # Contrôleurs (ex: _profil.ctrl.php, etc.)
│  ├─ Models/              # Accès aux données (PDO) + MongoDB
│  ├─ Views/               # Vues PHP/partials (Bootstrap)
│  └─ Services/            # Mailer, sécurité, helpers
├─ config/                 # Bootstrap env, constantes, routes
├─ database/
│  ├─ schema.sql           # Création tables SQL
│  └─ seed.sql             # Données d’exemple (optionnel)
├─ public/                 # Racine web (index.php, assets, .htaccess)
├─ storage/
│  └─ logs/                # Logs applicatifs
├─ vendor/                 # Dépendances Composer
├─ package.json            # Scripts NPM (Sass…)
├─ composer.json           # Dépendances PHP
├─ .env.example            # Exemple de configuration d’environnement
└─ README.md               # (ce fichier)
```

## Prérequis
- **PHP 8.1+** avec extensions : `pdo_mysql`, `openssl`, `mbstring`, `json`, `curl`, `intl`, `fileinfo`, `mongodb`
- **Composer** (≥ 2.5)
- **Node.js + npm** (pour compiler Sass)
- **Apache** avec `mod_rewrite` **activé** (ou équivalent via Nginx)
- **MySQL/MariaDB** (local) + **MongoDB** (local)

> Sous **Windows (XAMPP)** : activer `extension=mongodb` dans le bon `php.ini`, puis `composer require mongodb/mongodb`.

## Installation locale (pas à pas)

### 1) Cloner & installer les dépendances
```bash
# 1. Cloner
git clone https://github.com/votre-compte/ecoride.git
cd ecoride

# 2. PHP (Composer)
composer install --no-dev # ou avec --dev si vous voulez les outils qualité

# 3. Front (Sass)
npm install
npm run build   # ou npm run dev / npm run sass:watch selon vos scripts
```

### 2) Configuration `.env`
Copiez le modèle et renseignez **vos** valeurs.
```bash
cp .env.example .env
```
Variables utilisées (exemple) :
```dotenv
# App
APP_ENV=local
BASE_URL=http://localhost/ecoride    # ou http://localhost:8000 si serveur PHP intégré
APP_KEY=changez-moi-en-chaine-aleatoire-longue
CSRF_SECRET=une-autre-chaine-aleatoire
SESSION_NAME=ecoride_session

# SQL
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=ecoride
DB_USER=root
DB_PASS=

# MongoDB
MONGO_URI=mongodb://127.0.0.1:27017
MONGO_DB=ecoride

# Mail (PHPMailer)
SMTP_HOST=smtp.votrefai.fr
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=postmaster@exemple.fr
SMTP_PASS=********
SMTP_FROM=noreply@ecoride.local
SMTP_FROM_NAME=EcoRide

# Sécurité HTTP
CSP_DEFAULT_SRC='self'
```

### 3) Base de données SQL (MySQL/MariaDB)
1. Créez la base `ecoride`.
2. Importez `database/schema.sql` (création des tables).
3. Optionnel : importez `database/seed.sql` (données de démo, ex. villes, comptes tests, trajets).

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS ecoride CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p ecoride < database/schema.sql
# Optionnel
mysql -u root -p ecoride < database/seed.sql
```

### 4) Base NoSQL (MongoDB)
- Démarrez MongoDB en local.
- Créez la base `ecoride` et, au premier démarrage, les collections seront créées à l’écriture (ex: `avis`, `driver_stats`).
- Vérifiez la connexion via `MONGO_URI`.

### 5) Configuration Apache / Routage
EcoRide utilise un **front‑controller** (`public/index.php`) et un routeur qui lit l’URL via `index.php?url=...`.

**.htaccess (dans `public/`)** :
```apacheconf
# Activer la réécriture d’URL
RewriteEngine On

# Si votre app est dans un sous-dossier, adaptez RewriteBase
# Exemples :
# RewriteBase /ecoride/

# Laisser passer les fichiers/dossiers existants
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Tout le reste -> index.php?url=...
RewriteRule ^(.+)$ index.php?url=$1 [QSA,L]
# (Si votre routeur lit directement PATH_INFO, utilisez `RewriteRule ^ index.php [QSA,L]`)
```

**VirtualHost (exemple)** :
```apacheconf
<VirtualHost *:80>
  ServerName ecoride.local
  DocumentRoot "C:/xampp/htdocs/ecoride/public"

  <Directory "C:/xampp/htdocs/ecoride/public">
    AllowOverride All
    Require all granted
  </Directory>
</VirtualHost>
```
Ajoutez `127.0.0.1 ecoride.local` à votre fichier `hosts`.

### 6) Lancer l’application en local
- **Apache** : démarrer Apache/MySQL (XAMPP) puis ouvrir `http://ecoride.local` (ou `http://localhost/ecoride`).
- **Serveur PHP intégré (alternative)** :
  ```bash
  php -S localhost:8000 -t public
  ```
  > Selon la configuration, certaines réécritures avancées peuvent nécessiter Apache.

## Comptes de test & création de l’admin
La création du **compte administrateur** se fait **hors application**. Deux options :

1. **Fichier `seed.sql`** : il insère un admin (`admin@ecoride.local`).
2. **Insertion manuelle** :
   - Générez un hash :
     ```bash
     php -r "echo password_hash('Admin123!', PASSWORD_DEFAULT), PHP_EOL;"
     ```
   - Insérez dans votre table `utilisateurs` (adaptez noms de colonnes) :
     ```sql
     INSERT INTO utilisateurs (pseudo, email, mot_de_passe_hash, role, created_at)
     VALUES ('admin', 'admin@ecoride.local', 'VOTRE_HASH_ICI', 'admin', NOW());
     ```

> Des **comptes de démo** (utilisateur/employé) peuvent être fournis via `seed.sql`. Sinon, créez-les via l’interface « Créer un compte ».

## Qualité & outils dev
- **Lint PHP** : `php -l app/Controllers/_profil.ctrl.php`
- **PHPCS / PHPStan** : si configurés, `composer phpcs` / `composer phpstan`
- **Sass** : `npm run sass:watch`
- **Logs** : `storage/logs/app.log` (selon config)

## Sécurité (rappels de mise en œuvre)
- **Mots de passe** : `password_hash()` + `password_verify()` ; complexité minimale en front et back
- **PDO** : requêtes **préparées** partout (anti‑SQLi)
- **CSRF** : jetons sur tous les POST sensibles (`CSRF_SECRET`)
- **XSS** : échapper la sortie (`htmlspecialchars`) ; éviter l’**inline JS** ;
- **CSP** : définir une **Content‑Security‑Policy** stricte (pas d’inline, ou alors `nonce`/`sha256`)
- **Cookies** : `HttpOnly`, `Secure` (en HTTPS), `SameSite=Lax/Strict`
- **Mails** : PHPMailer en SMTP **TLS** (`AuthType=LOGIN` si besoin)
- **Comptes** : verrous opérationnels (ex: annulations, places/crédits), vérifications d’état

## Dépannage (FAQ)
**404 partout / routes KO**  
→ Vérifiez `AllowOverride All`, `.htaccess`, `mod_rewrite`, et la règle `index.php?url=$1`.

**Inline script bloqué par la CSP**  
→ Retirez le JS inline ou ajoutez un `nonce`/`sha256` à la CSP.

**PHPMailer n’envoie pas**  
→ Testez les variables SMTP ; port 587 + TLS ; journalisez `$mail->ErrorInfo`.

**MongoDB “Class MongoDB\Client introuvable”**  
→ Activez `extension=mongodb` et exécutez `composer require mongodb/mongodb`.

**Accents/encodage**  
→ Enregistrez les fichiers en **UTF‑8** sans BOM ; base en `utf8mb4_unicode_ci`.

## Kanban & documentation
- **Lien Kanban** : (À compléter)
- **Manuel d’utilisation (PDF)** : `docs/manuel-utilisation.pdf`
- **Charte graphique (PDF)** : `docs/charte-graphique.pdf`
- **Modèles & diagrammes** : `docs/`

## Licence
Usage pédagogique pour l’ECF. © Vous. Tous droits réservés.

