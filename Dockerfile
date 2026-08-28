FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    FINAL_FLAG=SRC{W33lc0m3_t0_SRC_h4ck3r!!}

RUN a2enmod headers \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY php.ini /usr/local/etc/php/conf.d/zz-cyber-arena.ini
COPY apache-security.conf /etc/apache2/conf-available/cyber-arena.conf
RUN a2enconf cyber-arena

WORKDIR /var/www/html
COPY app ./app
COPY public ./public

RUN chown -R root:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 550 {} \; \
    && find /var/www/html -type f -exec chmod 440 {} \;
