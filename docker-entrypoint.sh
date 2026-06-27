#!/bin/sh
set -e

mkdir -p /tmp/run/php /tmp/nginx-logs /var/www/html/storage /var/www/html/storage/logs /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /tmp/run /tmp/nginx-logs
chmod -R 0777 /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
