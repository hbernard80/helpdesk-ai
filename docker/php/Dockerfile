# Image officielle PHP 8.5 avec Apache
FROM php:8.5-apache

# Installation des extensions PHP pour MySQL
RUN docker-php-ext-install pdo_mysql

# Activation du module Apache rewrite
RUN a2enmod rewrite

# Définition du répertoire de travail
WORKDIR /var/www/html

# Copie du code source PHP
COPY src/ /var/www/html/

# Configuration du dossier public comme racine web
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Port HTTP du conteneur
EXPOSE 80
