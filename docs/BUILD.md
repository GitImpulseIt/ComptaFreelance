# Build & Déploiement - ComptaV2

## Prérequis

- Docker & Docker Compose
- Node.js (pour le build Tailwind CSS en local)

## Démarrage rapide

```bash
# 1. Configurer l'environnement
cp .env.example .env

# 2. Builder et démarrer les containers
docker compose build
docker compose up -d

# 3. Installer les dépendances PHP (app + admin)
docker compose exec php composer install
docker compose exec admin-php composer install

# 4. Initialiser la base de données
docker compose exec -T postgres psql -U comptav2 -d comptav2 < database/schema.sql

# 5. Builder le CSS Tailwind (app + admin)
npm install && npm run build
cd admin && npm install && npm run build && cd ..
```

Accès :
- **App freelance** : `http://localhost:8080`
- **App admin** : `http://localhost:8081` (mot de passe par défaut : `admin`)

## Services Docker

| Service | Image | Port | Description |
|---|---|---|---|
| **nginx** | `nginx:alpine` | `8080:80` | Reverse proxy app freelance |
| **php** | Build `docker/php/Dockerfile` | `9000` (interne) | PHP-FPM app freelance |
| **admin-nginx** | `nginx:alpine` | `8081:80` | Reverse proxy app admin |
| **admin-php** | Build `admin/docker/php/Dockerfile` | `9000` (interne) | PHP-FPM app admin |
| **postgres** | `postgres:16-alpine` | `5432:5432` | Base de données partagée |

## Commandes utiles

### Docker

```bash
# Démarrer tous les services
docker compose up -d

# Arrêter tous les services
docker compose down

# Arrêter et supprimer les volumes (reset BDD)
docker compose down -v

# Voir les logs
docker compose logs -f              # Tous les services
docker compose logs -f php          # App freelance
docker compose logs -f admin-php    # App admin
docker compose logs -f postgres     # BDD

# Accéder aux containers
docker compose exec php sh          # Shell app freelance
docker compose exec admin-php sh    # Shell app admin
docker compose exec postgres psql -U comptav2 -d comptav2   # PostgreSQL
```

### PHP / Composer

```bash
# App freelance
docker compose exec php composer install
docker compose exec php composer update
docker compose exec php composer dump-autoload

# App admin
docker compose exec admin-php composer install
docker compose exec admin-php composer update
docker compose exec admin-php composer dump-autoload
```

### CSS / Tailwind

Les deux webapps ont chacune leur propre build Tailwind.

```bash
# App freelance
npm install
npm run build           # Production (minifié)
npm run dev             # Watcher développement

# App admin
cd admin
npm install
npm run build
npm run dev
```

Tailwind v4 est configuré via `src/css/input.css` (dans chaque webapp) :
- `@import "tailwindcss"` charge le framework
- `@source "../../templates"` scanne les templates Twig

### Base de données

```bash
# Exécuter le schéma initial
docker compose exec -T postgres psql -U comptav2 -d comptav2 < database/schema.sql

# Exécuter une migration
docker compose exec -T postgres psql -U comptav2 -d comptav2 < database/migrations/XXXX_nom.sql
```

## Variables d'environnement

Définies dans `.env` (copié depuis `.env.example`) :

| Variable | Défaut | Description |
|---|---|---|
| `APP_ENV` | `dev` | Environnement (`dev`, `prod`) |
| `APP_PORT` | `8080` | Port app freelance |
| `ADMIN_PORT` | `8081` | Port app admin |
| `POSTGRES_HOST` | `postgres` | Hôte BDD (nom du service Docker) |
| `POSTGRES_PORT` | `5432` | Port interne PostgreSQL |
| `POSTGRES_PORT_EXTERNAL` | `5432` | Port exposé sur l'hôte |
| `POSTGRES_DB` | `comptav2` | Nom de la base |
| `POSTGRES_USER` | `comptav2` | Utilisateur BDD |
| `POSTGRES_PASSWORD` | `changeme` | Mot de passe BDD |
| `BANK_PROVIDER` | `none` | Provider API bancaire |
| `BANK_CLIENT_ID` | | Client ID du provider |
| `BANK_CLIENT_SECRET` | | Client secret du provider |

## Structure Docker

```
docker/                         # Docker app freelance
├── php/
│   ├── Dockerfile              # PHP 8.3-FPM Alpine + pdo_pgsql, intl, gd, zip
│   └── php.ini                 # Config PHP (timezone, upload_max, erreurs)
├── nginx/
│   └── default.conf            # Vhost → public/, proxy php:9000
└── postgres/
    └── init.sql                # Extensions PostgreSQL (uuid-ossp, pgcrypto)

admin/docker/                   # Docker app admin
├── php/
│   ├── Dockerfile              # PHP 8.3-FPM Alpine + pdo_pgsql, intl
│   └── php.ini
└── nginx/
    └── default.conf            # Vhost → admin/public/, proxy admin-php:9000
```

## Authentification admin

L'app admin utilise une authentification par mot de passe unique :

- Le hash SHA256 est stocké dans `admin/config/auth.php`
- Mot de passe par défaut : `admin`
- Modifiable via l'interface admin (menu "Changer le mot de passe")
- Reset des mots de passe utilisateurs : génère un mot de passe aléatoire affiché **une seule fois**
