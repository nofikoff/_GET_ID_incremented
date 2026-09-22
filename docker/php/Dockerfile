FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl \
    && rm -rf /var/lib/apt/lists/*

# The image ships no php.ini, so the 128M built-in default applies, and Larastan needs more.
RUN echo 'memory_limit = 512M' > "$PHP_INI_DIR/conf.d/memory-limit.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match the host user so files written into the bind mount (vendor/, storage/) stay host-owned on Linux.
ARG UID=1000
ARG GID=1000
RUN groupadd --non-unique --gid "${GID}" app \
    && useradd --non-unique --uid "${UID}" --gid "${GID}" --create-home app

WORKDIR /var/www/html

USER app
