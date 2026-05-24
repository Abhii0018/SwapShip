#!/usr/bin/env sh
set -e

cd /var/www

php artisan storage:link 2>/dev/null || true
php artisan migrate --force --no-interaction 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan optimize:clear 2>/dev/null || true

php artisan queue:restart 2>/dev/null || true
php artisan queue:work --tries=3 --timeout=60 --sleep=2 --backoff=5 --queue=default,mail >> /var/www/storage/logs/queue.log 2>&1 &

exec php -S 0.0.0.0:"${PORT:-10000}" -t public
