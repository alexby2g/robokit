FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql intl mbstring zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/robokit-laravel.conf \
    && a2enconf robokit-laravel

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /var/www/html/render-start.sh

EXPOSE 10000
CMD ["/var/www/html/render-start.sh"]
