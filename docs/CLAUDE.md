# Instructions LLM - ComptaV2

## Projet

- Webapp de comptabilité pour freelance avec backend admin multi-entreprises
- Technologies : PHP 8.3 / Twig / Tailwind CSS
- Base de données : PostgreSQL 16
- Environnement : Docker (PHP-FPM + Nginx + PostgreSQL)

## Règles de développement

- Les langages doivent toujours être dans des fichiers séparés (HTML/PHP/CSS/JS, etc.)
- Toujours commit et push après une modification

## Documentation

- Toute la documentation va dans le répertoire `docs/` et jamais ailleurs
- Ce fichier contient uniquement les instructions pour le LLM et certaines informations techniques
- Ce fichier ne peut être enrichi que sur demande explicite de l'utilisateur ; le LLM peut suggérer un ajout mais ne doit pas le faire sans poser la question

## Architecture

```
ComptaV2/
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   └── php.ini
│   ├── nginx/
│   │   └── default.conf
│   └── postgres/
│       └── init.sql
│
├── public/                          # Document root (seul répertoire exposé)
│   ├── index.php                    # Front controller unique
│   ├── css/app.css
│   ├── js/app.js
│   └── images/
│
├── src/
│   ├── Controller/
│   │   ├── App/                     # Espace freelance
│   │   ├── Admin/                   # Espace admin
│   │   └── Auth/                    # Authentification
│   ├── Model/                       # Entités métier
│   ├── Repository/                  # Accès BDD (requêtes SQL)
│   ├── Service/
│   │   ├── Banque/                  # Intégration bancaire
│   │   │   └── Parser/             # Parsers de fichiers (CSV, OFX, QIF)
│   │   └── Admin/                   # Logique admin
│   └── Middleware/                  # Auth, CSRF, contexte entreprise
│
├── templates/
│   ├── layout/                      # Layouts (base, app, admin)
│   ├── components/                  # Composants réutilisables
│   ├── auth/
│   ├── app/                         # Vues espace freelance
│   │   ├── dashboard/
│   │   ├── clients/
│   │   ├── factures/
│   │   ├── depenses/
│   │   ├── banque/
│   │   ├── tva/
│   │   └── parametres/
│   └── admin/                       # Vues espace admin
│       ├── dashboard/
│       ├── entreprises/
│       ├── users/
│       └── banque/
│
├── config/                          # Configuration applicative
├── database/                        # Migrations et schéma SQL
├── storage/                         # Logs, cache, uploads (non versionné)
│   └── uploads/banque/
├── docs/                            # Documentation
│
├── docker-compose.yml
├── .env / .env.example
├── composer.json
├── package.json
├── tailwind.config.js
├── .gitignore
└── .dockerignore
```

## Routes

- `/auth/*` — authentification (login, register)
- `/app/*` — espace freelance (scopé par entreprise)
- `/admin/*` — espace admin (gestion des entreprises/utilisateurs)

## Docker

```bash
cp .env.example .env
docker compose up -d
docker compose exec php composer install
```

- **php** : PHP 8.3-FPM (port 9000 interne)
- **nginx** : reverse proxy (port 8080)
- **postgres** : PostgreSQL 16 (port 5432)
