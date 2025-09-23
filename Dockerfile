ARG BUILD_ID=1
FROM php:8.3-apache

# 1) Paquets système + extensions PHP
RUN apt-get update && apt-get install -y libssl-dev pkg-config git unzip \
 && docker-php-ext-install pdo pdo_mysql \
 && pecl install mongodb \
 && docker-php-ext-enable mongodb \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# 2) DocumentRoot + AllowOverride
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && printf "\n<Directory ${APACHE_DOCUMENT_ROOT}>\n\tAllowOverride All\n</Directory>\n" >> /etc/apache2/apache2.conf

# 3) Composer (si tu n’as pas committé vendor/)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4) Code
WORKDIR /var/www/html
COPY . /var/www/html

# 5) Installer les dépendances APRES avoir activé ext-mongodb
#    (si vendor/ N’EST PAS committé)
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
