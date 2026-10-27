FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql gd zip opcache \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Document root de l'application : public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Le .htaccess de public/ (routage vers index.php, en-têtes de sécurité) n'est lu que si AllowOverride l'autorise.
RUN printf '<Directory %s>\n    AllowOverride All\n    Require all granted\n</Directory>\n' "${APACHE_DOCUMENT_ROOT}" >/etc/apache2/conf-available/app.conf \
    && a2enconf app \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/securite.conf \
    && a2enconf securite \
    && printf 'expose_php = Off\n' > /usr/local/etc/php/conf.d/securite.ini

WORKDIR /var/www/html
COPY . /var/www/html

# Autoloader maison ; vendor/ (TCPDF) est versionné dans le dépôt : aucune installation Composer n'est nécessaire.

RUN mkdir -p storage/cache storage/documents storage/logs storage/tenants public/uploads/branding \
    && chown -R www-data:www-data storage public/uploads \
    && chmod -R 775 storage public/uploads

EXPOSE 80
