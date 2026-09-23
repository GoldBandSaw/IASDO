FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_pgsql curl \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && a2enmod rewrite headers

WORKDIR /var/www/html
COPY . /var/www/html/
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
