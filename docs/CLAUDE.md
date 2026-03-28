# Instructions LLM - ComptaV2

## Projet

- Webapp de comptabilité pour freelance
- Webapp admin séparée (image Docker distincte) pour gérer les entreprises/utilisateurs
- Technologies : PHP 8.3 / Twig / Tailwind CSS v4
- Base de données : PostgreSQL 16 (partagée entre les deux webapps)
- Environnement : Docker (2x PHP-FPM + 2x Nginx + 1x PostgreSQL)

## Règles de développement

- Les langages doivent toujours être dans des fichiers séparés (HTML/PHP/CSS/JS, etc.)
- Toujours commit et push après une modification
- Toujours recompiler Tailwind après une modification de template ou de CSS (`npm run build` pour l'app, `cd admin && npm run build` pour l'admin)

## Documentation

- Toute la documentation va dans le répertoire `docs/` et jamais ailleurs
- Ce fichier contient uniquement les instructions pour le LLM et certaines informations techniques
- Ce fichier ne peut être enrichi que sur demande explicite de l'utilisateur ; le LLM peut suggérer un ajout mais ne doit pas le faire sans poser la question

## Architecture

Deux webapps indépendantes partageant la même base de données :

```
ComptaV2/
├── public/                     # App freelance - document root
├── src/                        # App freelance - code PHP
│   ├── Controller/App/         #   Controllers espace freelance
│   ├── Controller/Auth/        #   Controllers authentification
│   ├── Model/                  #   Entités métier
│   ├── Repository/             #   Accès BDD
│   ├── Service/Banque/         #   Intégration bancaire (parsers, import, connect)
│   └── Middleware/             #   Auth, CSRF, contexte entreprise
├── templates/                  # App freelance - templates Twig
├── config/                     # App freelance - configuration
├── docker/                     # Docker app freelance (php, nginx, postgres)
│
├── admin/                      # Webapp admin (image Docker séparée)
│   ├── public/                 #   Document root admin
│   ├── src/Controller/         #   Controllers admin
│   ├── src/Service/            #   Services admin (password, users, entreprises, banque)
│   ├── src/Middleware/         #   Auth admin (SHA256)
│   ├── templates/              #   Templates admin
│   ├── config/                 #   Configuration admin (dont auth.php avec hash SHA256)
│   └── docker/                 #   Docker admin (php, nginx)
│
├── database/                   # Schéma SQL et migrations (partagé)
├── storage/                    # Logs, cache, uploads app freelance
├── docs/                       # Documentation
└── docker-compose.yml          # Orchestre les 5 services
```

## Docker

- **App freelance** : `http://localhost:8080` (nginx + php)
- **App admin** : `http://localhost:8081` (admin-nginx + admin-php)
- **PostgreSQL** : `localhost:5432` (partagé)

## Auth admin

- Mot de passe stocké en SHA256 dans la table `admin_settings` (clé `password_hash`)
- Par défaut : `admin`
- Changeable via l'interface admin (`/password`)
- Reset des mots de passe utilisateurs : génère un mot de passe aléatoire affiché une seule fois
