# Install Composer dependencies in a separate stage so Composer itself is not in the final image.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --optimize

FROM php:8.3-apache

# Serve only public/; send every request that is not a real file to the front controller.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && echo 'FallbackResource /index.php' > /etc/apache2/conf-available/app.conf \
    && a2enconf app \
    && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/security.ini"

WORKDIR /var/www/html
COPY --from=vendor /app .
RUN mkdir -p storage/cache && chown -R www-data:www-data storage

EXPOSE 80
