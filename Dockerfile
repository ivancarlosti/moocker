FROM php:8.2-apache

# Install required packages and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libicu-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli zip gd intl soap opcache exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set recommended OPcode cache for Moodle
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=60'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Increase PHP limits for Moodle
RUN { \
    echo 'max_execution_time = 300'; \
    echo 'memory_limit = 512M'; \
    echo 'post_max_size = 50M'; \
    echo 'upload_max_filesize = 50M'; \
    echo 'max_input_vars = 5000'; \
    echo 'zend.exception_ignore_args = On'; \
} > /usr/local/etc/php/conf.d/moodle-php.ini

# Allow fetching a specific Moodle branch or tag dynamically
ARG MOODLE_VERSION=v5.1.3
ENV MOODLE_VERSION=${MOODLE_VERSION}

# Clone Moodle source code
WORKDIR /var/www/html
RUN rm -rf * \
    && git clone --depth=1 --branch ${MOODLE_VERSION} git://git.moodle.org/moodle.git . \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && composer install --no-dev --classmap-authoritative --no-interaction

# Create moodledata directory
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata \
    && chmod -R 777 /var/www/moodledata

# Update Apache configuration to point to Moodle public directory
# Moodle 5.0+ requires DocumentRoot to be /var/www/html/public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80

CMD bash -c "chown -R www-data:www-data /var/www/moodledata && apache2-foreground"
