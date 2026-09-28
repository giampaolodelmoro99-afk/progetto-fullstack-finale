FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip gd exif pcntl opcache \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN groupadd --gid "${GID}" app \
    && useradd --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash app \
    && mkdir -p /var/www/html /home/app/.composer \
    && chown -R app:app /var/www/html /home/app

ENV COMPOSER_HOME=/home/app/.composer \
    PHP_CLI_SERVER_WORKERS=4

USER app

WORKDIR /var/www/html

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
