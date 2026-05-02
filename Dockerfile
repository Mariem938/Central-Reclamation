FROM php:8.2-apache

WORKDIR /var/www/html
COPY . .

RUN docker-php-ext-install mysqli pdo pdo_mysql

# ✅ Autoriser accès Apache
RUN echo "<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" > /etc/apache2/conf-available/custom.conf \
&& a2enconf custom

# ✅ Activer rewrite (important pour PHP frameworks)
RUN a2enmod rewrite

# ✅ Permissions
RUN chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]