@echo off
title ComptaV2 - Dev Environment
echo ============================================
echo   ComptaV2 - Lancement environnement dev
echo ============================================
echo.

REM Copier .env si inexistant
if not exist ".env" (
    echo [*] Creation du .env depuis .env.example...
    copy .env.example .env
)

REM Build et demarrage des containers
echo [*] Build et demarrage des containers...
docker compose up -d --build
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERREUR] Echec du demarrage. Docker Desktop est-il lance ?
    pause
    exit /b 1
)

REM Installation des dependances PHP
echo [*] Installation des dependances PHP (app + admin)...
docker compose exec php composer install --no-interaction --quiet
docker compose exec admin-php composer install --no-interaction --quiet

REM Initialisation de la BDD (idempotent via IF NOT EXISTS dans le schema)
echo [*] Initialisation de la base de donnees...
docker compose exec -T postgres psql -U comptav2 -d comptav2 -f /docker-entrypoint-initdb.d/init.sql 2>nul

REM Build Tailwind CSS
echo [*] Build Tailwind CSS (app principale)...
call npm install --silent 2>nul
call npm run build

echo [*] Build Tailwind CSS (admin)...
pushd admin
call npm install --silent 2>nul
call npm run build
popd

echo.
echo ============================================
echo   Environnement dev pret !
echo.
echo   App principale : http://localhost:8080
echo   Admin          : http://localhost:8081
echo   PostgreSQL     : localhost:5432
echo   Mot de passe admin par defaut : admin
echo ============================================
echo.
pause
