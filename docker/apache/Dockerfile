FROM php:8.2-apache

# Install PHP extensions and tools
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mbstring mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Create log directory and app.log with permissions for Apache (www-data)
RUN mkdir -p /var/log/librebooking/log && \
    touch /var/log/librebooking/log/app.log && \
    chown -R www-data:www-data /var/log/librebooking && \
    chmod -R 775 /var/log/librebooking

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /entrypoint.sh

# Make entrypoint executable
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /var/www

# Use custom entrypoint
ENTRYPOINT ["/entrypoint.sh"]
