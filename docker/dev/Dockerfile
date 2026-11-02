# Development image: PHP 8.4 CLI + extensions + Composer + Node 24 + ffmpeg.
FROM php:8.4-cli

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg git unzip sqlite3 \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl zip gd exif bcmath pcntl pdo_mysql pdo_pgsql redis pcov

RUN { \
      echo 'memory_limit=1G'; \
      echo 'upload_max_filesize=64M'; \
      echo 'post_max_size=64M'; \
    } > /usr/local/etc/php/conf.d/zz-dev.ini

ENV COMPOSER_ALLOW_SUPERUSER=1 HOME=/tmp
WORKDIR /app
