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

# 3. Installer les dépendances PHP
docker compose exec php composer install

# 4. Initialiser la base de données
docker compose exec -T postgres psql -U comptav2 -d comptav2 < database/schema.sql

# 5. Builder le CSS (Tailwind)
npm install
npm run build
```

L'application est accessible sur `http://localhost:8080`.

## Services Docker

| Service | Image | Port | Description |
|---|---|---|---|
| **nginx** | `nginx:alpine` | `8080:80` | Reverse proxy, sert les assets statiques |
| **php** | Build `docker/php/Dockerfile` | `9000` (interne) | PHP-FPM 8.3, exécute le code applicatif |
| **postgres** | `postgres:16-alpine` | `5432:5432` | Base de données PostgreSQL |

## Commandes utiles

### Docker

```bash
# Démarrer les services
docker compose up -d

# Arrêter les services
docker compose down

# Arrêter et supprimer les volumes (reset BDD)
docker compose down -v

# Voir les logs
docker compose logs -f
docker compose logs -f php
docker compose logs -f nginx
docker compose logs -f postgres

# Accéder au container PHP
docker compose exec php sh

# Accéder à PostgreSQL
docker compose exec postgres psql -U comptav2 -d comptav2
```

### PHP / Composer

```bash
# Installer les dépendances
docker compose exec php composer install

# Mettre à jour les dépendances
docker compose exec php composer update

# Autoload après ajout d'une classe
docker compose exec php composer dump-autoload
```

### CSS / Tailwind

```bash
# Installer les dépendances Node
npm install

# Builder le CSS (production, minifié)
npm run build

# Watcher en développement (recompile à chaque modification)
npm run dev
```

Tailwind v4 est configuré via `src/css/input.css` :
- `@import "tailwindcss"` charge le framework
- `@source "../../templates"` scanne les templates Twig pour les classes utilisées

Le CSS compilé est généré dans `public/css/app.css`.

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
| `APP_PORT` | `8080` | Port exposé par Nginx |
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
docker/
├── php/
│   ├── Dockerfile       # PHP 8.3-FPM Alpine + extensions pdo_pgsql, intl, gd, zip
│   └── php.ini          # Config PHP (timezone, upload_max, erreurs)
├── nginx/
│   └── default.conf     # Vhost : root sur public/, proxy PHP-FPM
└── postgres/
    └── init.sql         # Extensions PostgreSQL (uuid-ossp, pgcrypto)
```
