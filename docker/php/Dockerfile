FROM php:8.3-fpm-bookworm

# Ekstensi PHP umum Laravel via helper (ringkas & mudah dirawat)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_mysql mbstring bcmath gd zip intl opcache pcntl exif

# Ghostscript: engine tool PDF (gabung, pisah, kompres)
RUN apt-get update \
    && apt-get install -y --no-install-recommends ghostscript \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Samakan UID/GID container dengan host agar file tidak jadi milik root
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g ${GID} www-data

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
