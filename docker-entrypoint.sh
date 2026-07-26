#!/bin/sh
set -eu

echo "--- docker-entrypoint.sh: preparing environment ---"

# Create required runtime directories (bind-mounted volumes may not exist yet)
mkdir -p /tmp/run/php /tmp/nginx-logs \
    /var/www/html/storage \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

echo "Runtime directories created"

# Fix ownership for writable runtime directories only (not the entire project)
chown www-data:www-data \
    /tmp/run /tmp/run/php \
    /tmp/nginx-logs

echo "Runtime permissions set for www-data"

# Ensure storage and cache are writable
chmod -R 0777 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Storage and cache directories are writable"

# Clear stale bootstrap cache to avoid referencing dev-only packages (e.g., Collision)
rm -f /var/www/html/bootstrap/cache/packages.php /var/www/html/bootstrap/cache/services.php
echo "Stale bootstrap cache cleared"

# Wait for database to be ready
db_ready() {
    php /var/www/html/artisan db:show --quiet 2>/dev/null
}

echo "Waiting for database connection..."
for i in $(seq 1 30); do
    if db_ready; then
        echo "Database connection established"
        break
    fi
    echo "Attempt $i: Database not ready yet, waiting..."
    sleep 2
done

if ! db_ready; then
    echo "WARNING: Database connection could not be confirmed after 60 seconds."
    echo "Attempting to proceed anyway..."
fi

# Run database migrations
echo "Running database migrations..."
php /var/www/html/artisan migrate --force
echo "Migrations completed"

# Seed countries if the table is empty
echo "Seeding countries..."
php /var/www/html/artisan db:seed --force
echo "Countries seeding completed"

# Execute the main container command (from CMD in Dockerfile)
echo "Starting application: $@"
exec "$@"
