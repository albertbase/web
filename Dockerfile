FROM php:8.3-fpm AS builder

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    nodejs \
    npm \
    libicu-dev \
    && docker-php-ext-install pdo pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-dev --optimize-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Build-time env only (removed from final image; Railway supplies real values at runtime)
COPY .env.docker .env

RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts
RUN NODE_ENV=production npm run build
RUN php bin/console cache:warmup --env=prod --no-debug

FROM php:8.3-fpm AS runtime

WORKDIR /app

RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    libicu-dev \
    && docker-php-ext-install pdo pdo_mysql intl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /app /app

RUN rm -f /app/.env && \
    mkdir -p /app/var /app/public/uploads/products /app/public/uploads/cake-previews && \
    chown -R www-data:www-data /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/var /app/public/uploads

COPY nginx-main.conf /etc/nginx/nginx.conf

RUN rm -rf /etc/nginx/conf.d/* /etc/nginx/sites-enabled /etc/nginx/sites-available
COPY nginx.conf /etc/nginx/conf.d/symfony.conf

COPY entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/healthcheck.sh

ENV PORT=80 \
    APP_ENV=prod \
    APP_DEBUG=0

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD ["/usr/local/bin/healthcheck.sh"]

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
