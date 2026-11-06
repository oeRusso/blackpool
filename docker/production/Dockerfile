# syntax=docker/dockerfile:1.7
# Production image for BlackPool: PHP-FPM 8.4 + ffmpeg, built assets, a Node
# SSR server and an Nginx image that serves /public and streams protected media.

FROM php:8.4-fpm-bookworm AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg default-mysql-client unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl zip gd exif bcmath pcntl pdo_mysql redis opcache
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-blackpool.ini
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-blackpool.conf
WORKDIR /var/www/html

# ---- Composer dependencies ------------------------------------------------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress

# ---- Front-end build (Wayfinder needs PHP, Vite needs Node) ---------------
FROM vendor AS assets
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY . .
# No .env in the image: a throwaway key is enough to boot artisan for Wayfinder.
RUN composer dump-autoload --no-dev --no-interaction \
    && APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" npm run build
# ^ builds the client assets (public/build) and the self-contained SSR bundle
#   (bootstrap/ssr), which the `ssr` stage below runs without node_modules.

# ---- Application (php-fpm, queue worker and scheduler) -------------------
FROM base AS app
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=assets /var/www/html/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && mkdir -p storage/app/public storage/app/private storage/app/media storage/framework/{cache/data,sessions,views} storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f /usr/local/bin/composer
COPY --chmod=755 docker/production/entrypoint.sh /usr/local/bin/entrypoint
USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---- Inertia SSR server (Node) ---------------------------------------------
FROM node:24-alpine AS ssr
WORKDIR /app
COPY --from=assets /var/www/html/bootstrap/ssr ./bootstrap/ssr
RUN echo '{"type":"module","private":true}' > package.json
USER node
EXPOSE 13714
CMD ["node", "bootstrap/ssr/app.js"]

# ---- Nginx -----------------------------------------------------------------
FROM nginx:1.27-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/production/nginx/cloudflare.conf /etc/nginx/snippets/cloudflare.conf
COPY docker/production/nginx/app.conf /etc/nginx/snippets/app.conf
COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage
