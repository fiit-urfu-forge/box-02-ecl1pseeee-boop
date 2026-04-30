#!/bin/sh
set -e

echo "[entrypoint] Waiting for Postgres at ${DB_HOST}:${DB_PORT}..."
until php -r "try { new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}'); exit(0); } catch (Throwable \$e) { exit(1); }" 2>/dev/null; do
    sleep 1
done
echo "[entrypoint] Postgres reachable."

cd /var/www/html

echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

USER_COUNT="$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tr -d '\n' | tail -c 12 | grep -oE '[0-9]+' | tail -1 || echo 0)"
if [ -z "$USER_COUNT" ] || [ "$USER_COUNT" = "0" ]; then
    if [ -d "${CSV_DATA_DIR:-/var/www/html/storage/app/data}" ]; then
        echo "[entrypoint] Importing CSV dataset..."
        php artisan loyalty:import || echo "[entrypoint] CSV import failed (continuing)."
    else
        echo "[entrypoint] CSV directory not found, skipping import."
    fi
else
    echo "[entrypoint] Users already loaded ($USER_COUNT) — skipping CSV import."
fi

# Skip config:cache in dev — it freezes env vars and prevents `php artisan test`
# (or any --env override) from switching DB connections inside this container.
# Re-enable only in a separate prod-build stage.
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true

echo "[entrypoint] Starting php-fpm."
exec "$@"
