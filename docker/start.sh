#!/usr/bin/env sh
set -e

cd /var/www

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

php artisan storage:link 2>/dev/null || true

# Run migrations and log full output so failures surface in Render logs.
echo "[start.sh] running migrations..."
php artisan migrate --force --no-interaction 2>&1 || echo "[start.sh] WARN: migrate failed (continuing)"

# Always clear caches; surface errors to logs.
php artisan view:clear 2>&1 || echo "[start.sh] WARN: view:clear failed"
php artisan optimize:clear 2>&1 || echo "[start.sh] WARN: optimize:clear failed"
php artisan config:clear 2>&1 || true
php artisan route:clear 2>&1 || true

php artisan queue:restart 2>/dev/null || true
php artisan queue:work --tries=3 --timeout=60 --sleep=2 --backoff=5 --queue=default,mail >> /var/www/storage/logs/queue.log 2>&1 &

exec php -S 0.0.0.0:"${PORT:-10000}" -t public public/server.php
