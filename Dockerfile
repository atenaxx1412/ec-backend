# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-configure intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Xdebug for development
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache
RUN a2enmod rewrite
RUN a2enmod headers

# Set PHP configuration for development
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/development.ini
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/development.ini
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/development.ini
RUN echo "error_log = /var/log/apache2/php_errors.log" >> /usr/local/etc/php/conf.d/development.ini
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/development.ini
RUN echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/development.ini
RUN echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/development.ini

# Configure Xdebug for development
RUN echo "xdebug.mode=debug,develop" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure mail to use Mailpit
RUN echo "sendmail_path = /usr/sbin/sendmail -t -i -S mailpit:1025" >> /usr/local/etc/php/conf.d/development.ini

# Create necessary directories
RUN mkdir -p /var/www/html/public
RUN mkdir -p /var/www/html/src
RUN mkdir -p /var/log/apache2

# Set proper permissions for development
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Copy custom Apache configuration
COPY config/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy custom PHP configuration
COPY config/php/development.ini /usr/local/etc/php/conf.d/development.ini

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]