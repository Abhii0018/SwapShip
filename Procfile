web: sh docker/start.sh
worker: php artisan queue:work --tries=3 --timeout=60 --sleep=2 --backoff=5 --queue=default,mail
