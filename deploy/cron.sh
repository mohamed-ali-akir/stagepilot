#!/bin/bash
# Scheduled task on the server (alwaysdata: Avancé > Tâches planifiées, every 10 minutes).
#  - schedule:run starts what is due (08:00 prepare, 09:00 read replies + queue the day's emails)
#  - in the morning, a worker sends the queued emails one by one, 1 to 3 minutes apart.
#    It stops after ~9.5 minutes; the next run takes over (alwaysdata skips a run while the previous one is still going).
#  - the rest of the day, waiting jobs are still processed within 10 minutes
cd "$HOME/stagepilot" || exit 1
# proof that the scheduled task really runs
TZ=Europe/Paris date '+%Y-%m-%d %H:%M' > storage/logs/derniere_tache.txt

php artisan schedule:run --no-interaction

hour=$(TZ=Europe/Paris date +%-H)
if [ "$hour" -ge 8 ] && [ "$hour" -lt 12 ]; then
    php artisan queue:work --max-time=570 --sleep=10 --tries=1 --no-interaction
else
    # the rest of the day: only what is waiting (a « Préparer » clicked in the evening), then stop
    php artisan queue:work --stop-when-empty --max-time=570 --tries=1 --no-interaction
fi
