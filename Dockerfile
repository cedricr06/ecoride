# --- Base image
FROM php:8.3-apache

# --- 1) Paquets + extensions PHP
RUN apt-get update && apt-get install -y libssl-dev pkg-config git unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# --- 2) Docroot = public + AllowOverride All (pour .htaccess)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && printf "\n<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n</Directory>\n" >> /etc/apache2/apache2.conf

# --- 3) Composer (si vendor/ n'est pas committé)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- 4) Code
WORKDIR /var/www/html
COPY . /var/www/html

# On ne déploie jamais le .env local en prod
RUN rm -f /var/www/html/.env

# --- 5) Dépendances PHP (prod)
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts

# --- 6) Faire écouter Apache sur le port injecté par Railway ($PORT)
#     (fallback sur 8080 en local Docker)
ENV PORT=8080
RUN sed -ri 's/^Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE ${PORT}
CMD ["apache2-foreground"]
