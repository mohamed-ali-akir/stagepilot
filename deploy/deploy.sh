#!/bin/bash
# Runs ON THE SERVER after `git push alwaysdata main` (see deploy/README.md). Never touches the database content.
set -e
cd "$HOME/stagepilot"

git --git-dir="$HOME/stagepilot.git" --work-tree="$HOME/stagepilot" checkout -f main

if [ ! -f .env ]; then
    cp deploy/env.production .env
    php artisan key:generate --force
    echo ">> .env créé : ajoute les adresses invitées dans INVITED_EMAILS"
fi
mkdir -p database storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
[ -f database/database.sqlite ] || touch database/database.sqlite

composer install --no-dev --optimize-autoloader --no-interaction --no-progress
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force
php artisan optimize

echo ">> StagePilot à jour"
