FROM php:8.2-apache

# Habilitar mod_rewrite si fuera necesario (aunque para este ejemplo simple no es estricto)
RUN a2enmod rewrite

# Copiar el código fuente
COPY . /var/www/html/

# Ajustar permisos si fuera necesario
RUN chown -R www-data:www-data /var/www/html
