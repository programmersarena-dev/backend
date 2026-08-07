#!/bin/sh
set -eu

log() {
    printf '%s - %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
}

log "Preparing environment"

# Runtime directories (named volumes can be empty on first boot)
mkdir -p /tmp/run/php /tmp/nginx-logs \
    /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache

chown -R www-data:www-data \
    /tmp/run /tmp/nginx-logs \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# Directories need +x to be traversable, files don't. 0775/0664 (not 0777)
# keeps things group-writable for www-data without going world-writable.
find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 0775 {} +
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 0664 {} +

log "Runtime directories ready"

# Stale bootstrap cache can reference dev-only packages (e.g. Collision)
# from a different composer install, or - worse - freeze config values
# (like DB_HOST) from a run outside this container, ignoring env vars
# entirely. Safe to drop: Laravel rebuilds all of these on demand.
rm -f /var/www/html/bootstrap/cache/packages.php \
      /var/www/html/bootstrap/cache/services.php \
      /var/www/html/bootstrap/cache/config.php \
      /var/www/html/bootstrap/cache/routes-v7.php \
      /var/www/html/bootstrap/cache/events.php

# Wait for the database. Every service (app, queue-worker, judge-consumer)
# uses this entrypoint, so this is a shared readiness gate for all of them.
db_ready() {
    php /var/www/html/artisan db:show --quiet >/dev/null 2>&1
}

log "Waiting for database connection..."
attempt=0
until db_ready; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        log "WARNING: database not reachable after 60s, proceeding anyway"
        break
    fi
    log "Attempt $attempt: database not ready yet, waiting..."
    sleep 2
done
if [ "$attempt" -lt 30 ]; then
    log "Database connection established"
fi

log "Starting: $*"
exec "$@"
