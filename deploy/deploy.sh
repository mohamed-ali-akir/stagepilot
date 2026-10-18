#!/bin/bash
# Runs ON THE SERVER after `git push alwaysdata main` (see deploy/README.md). Never touches the database content.
set -e
cd "$HOME/stagepilot"

git --git-dir="$HOME/stagepilot.git" --work-tree="$HOME/stagepilot" checkout -f main

if [ ! -f .env ]; then
    cp deploy/env.production .env
    echo ">> .env créé : ajoute les adresses invitées dans INVITED_EMAILS"
fi
mkdir -p database storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
[ -f database/database.sqlite ] || touch database/database.sqlite

composer install --no-dev --optimize-autoloader --no-interaction --no-progress
# after composer (artisan needs vendor/), and only once: a new key would make the stored Gmail passwords unreadable
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force
php artisan optimize
# The worker started by cron.sh can run for up to ~10 minutes with the OLD code in memory, against the database
# that was just migrated. This tells it to stop after its current job; the next cron run starts it with the new code.
php artisan queue:restart

echo ">> StagePilot à jour"
