FROM php:8.2-fpm-alpine3.18

# Optional: use alternate mirror
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories

# Install system dependencies
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
    && update-ca-certificates

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_pgsql zip opcache

# Install Docker CLI properly
RUN apk add --no-cache docker \
    && ln -s /usr/bin/docker /usr/local/bin/docker

# Install Composer
WORKDIR /tmp
RUN curl -sS https://getcomposer.org/installer  > composer-setup.php \
    && php -r "echo hash_file('sha384', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig'))  { echo 'Installer verified'; } else { echo 'Installer invalid'; unlink('composer-setup.php'); exit(1); }" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer --version

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Optional: Copy artisan
COPY artisan ./

# Copy minimal files needed for Artisan
COPY bootstrap/app.php vendor/autoload.php ./

# Fix for Git ownership error
RUN git config --global --add safe.directory /var/www/html

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --prefer-dist --no-scripts

# Copy all files
COPY . .

# Create runtime dirs (use tmp instead of /run)
RUN mkdir -p /tmp/run/php /tmp/nginx-logs \
    && chown -R www-data:www-data /tmp/run /tmp/nginx-logs

# Install Nginx
RUN apk add --no-cache nginx

# Create runtime directories
RUN mkdir -p /tmp/run/php /tmp/nginx-logs

# Clean up old PHP-FPM config
RUN rm -rf /usr/local/etc/php-fpm.d/*.conf

# Write fresh www.conf with proper [www] section
RUN echo "[www]" > /usr/local/etc/php-fpm.d/www.conf && \
    echo "user = www-data" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "listen = /tmp/run/php/php8.2-fpm.sock" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "listen.owner = www-data" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "listen.group = www-data" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "listen.mode = 0660" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.max_children = 5" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.start_servers = 2" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.min_spare_servers = 1" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "pm.max_spare_servers = 3" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "chdir = /" >> /usr/local/etc/php-fpm.d/www.conf

# Copy config files
COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/conf.d/default.conf

# Set user at the very end
RUN chown -R www-data:www-data /var/www/html /tmp/run /tmp/nginx-logs
# USER www-data
WORKDIR /var/www/html

# Expose port
EXPOSE 80

# Start services as root, then drop privileges
CMD ["sh", "-c", "mkdir -p /tmp/run/php && chown -R www-data:www-data /tmp/run /tmp/nginx-logs && php-fpm --fpm-config /usr/local/etc/php-fpm.conf --daemonize && nginx -g 'daemon off;'"]
