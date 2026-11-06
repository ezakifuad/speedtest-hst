FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql
COPY php.ini /usr/local/etc/php/conf.d/speedtest.ini
COPY public/ /var/www/html/
COPY app/ /var/www/app/
RUN a2enmod rewrite
COPY apache.conf /etc/apache2/conf-enabled/speedtest.conf
