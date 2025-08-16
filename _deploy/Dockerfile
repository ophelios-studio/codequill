FROM php:8.4-apache-bookworm

# Install required system packages
RUN apt-get update && apt-get install -y \
    git curl unzip zip locales \
    libonig-dev libcurl4-openssl-dev \
    libsqlite3-dev libpq-dev libicu-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    libxml2-dev zlib1g-dev libzip-dev \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Set timezone and locale
ENV TZ="America/Montreal"
RUN locale-gen en_CA.UTF-8 fr_CA.UTF-8 && update-locale

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install \
    gd intl pdo_pgsql pgsql curl zip xml mbstring exif gettext

# Optional: Enable APCu
RUN pecl install apcu && docker-php-ext-enable apcu

# Optional: SSH2 (only if needed)
RUN apt-get update && apt-get install -y libssh2-1-dev && \
    pecl install ssh2 && \
    docker-php-ext-enable ssh2 && \
    rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers expires

# Custom PHP config
#COPY custom.ini /usr/local/etc/php/conf.d/base-custom.ini

# Copy only composer files first to leverage Docker cache
#COPY composer.json composer.lock /var/www/html/
COPY composer.json /var/www/html/

# Apache vhost for Fly (port 80 only, no SSL block)
COPY _deploy/vhosts/default.conf /etc/apache2/sites-available/000-default.conf

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --working-dir=/var/www/html

# Copy app code
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html
RUN chmod +x _deploy/scripts/init-db.sh
#USER www-data

# Apache must be PID 1
EXPOSE 80
CMD ["apache2-foreground"]
