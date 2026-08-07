FROM php:8.4-fpm-alpine3.19 AS base

RUN apk add --no-cache \
        curl \
        unzip \
        git \
        openssh-client \
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

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql zip opcache \
    && docker-php-ext-enable opcache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /tmp
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php \
    && php -r '$expected = trim(file_get_contents("https://composer.github.io/installer.sig")); $actual = hash_file("sha384", "composer-setup.php"); if ($expected !== $actual) { echo "Installer invalid\n"; unlink("composer-setup.php"); exit(1); }' \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer --version

FROM base AS deps
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --prefer-dist \
        --no-dev \
        --no-scripts \
        --no-autoloader

FROM base AS runtime
WORKDIR /var/www/html

RUN rm -rf /usr/local/etc/php-fpm.d/*.conf \
    && { \
        echo '[www]'; \
        echo 'user = www-data'; \
        echo 'group = www-data'; \
        echo 'listen = /tmp/run/php/php-fpm.sock'; \
        echo 'listen.owner = www-data'; \
        echo 'listen.group = www-data'; \
        echo 'listen.mode = 0660'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 5'; \
        echo 'pm.start_servers = 2'; \
        echo 'pm.min_spare_servers = 1'; \
        echo 'pm.max_spare_servers = 3'; \
        echo 'chdir = /'; \
    } > /usr/local/etc/php-fpm.d/www.conf

COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/conf.d/default.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY --from=deps /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

RUN git config --global --add safe.directory /var/www/html \
    && mkdir -p /tmp/run/php /tmp/nginx-logs /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /tmp/run /tmp/nginx-logs

HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=3 \
    CMD curl -fs http://127.0.0.1/up || exit 1

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["sh", "-c", "php-fpm --fpm-config /usr/local/etc/php-fpm.conf --daemonize && nginx -g 'daemon off;'"]
