FROM php:8.2-fpm-alpine3.18 AS base

# Common system dependencies
RUN apk add --no-cache \
    curl \
    unzip \
    git \
    openssh \
    ca-certificates \
    openssl \
    libpng-dev \
    jpeg-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    docker-cli \
    nginx \
    && update-ca-certificates \
    && ln -sf /usr/bin/docker /usr/local/bin/docker

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_pgsql zip opcache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp

# Install Composer
WORKDIR /tmp
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php \
    && php -r '$expected = trim(file_get_contents("https://composer.github.io/installer.sig")); $actual = hash_file("sha384", "composer-setup.php"); if ($expected !== $actual) { echo "Installer invalid\n"; unlink("composer-setup.php"); exit(1); }' \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer --version

FROM base AS deps
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --prefer-dist --no-dev --no-scripts

FROM base AS runtime
WORKDIR /var/www/html
COPY --from=deps /var/www/html/vendor ./vendor
COPY . .
RUN git config --global --add safe.directory /var/www/html
RUN rm -rf /usr/local/etc/php-fpm.d/*.conf \
    && printf '%s\n' \
        '[www]' \
        'user = www-data' \
        'group = www-data' \
        'listen = /tmp/run/php/php8.2-fpm.sock' \
        'listen.owner = www-data' \
        'listen.group = www-data' \
        'listen.mode = 0660' \
        'pm = dynamic' \
        'pm.max_children = 5' \
        'pm.start_servers = 2' \
        'pm.min_spare_servers = 1' \
        'pm.max_spare_servers = 3' \
        'chdir = /' \
        > /usr/local/etc/php-fpm.d/www.conf
RUN mkdir -p /tmp/run/php /tmp/nginx-logs /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /tmp/run /tmp/nginx-logs
COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/conf.d/default.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["sh", "-c", "php-fpm --fpm-config /usr/local/etc/php-fpm.conf --daemonize && nginx -g 'daemon off;' "]
