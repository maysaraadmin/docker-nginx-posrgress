FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libxml2-dev \
    libxslt-dev \
    libicu-dev \
    libsodium-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        mbstring \
        fileinfo \
        sodium \
        zip \
        gd \
        intl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        exif \
        curl \
        simplexml \
        dom \
        xml \
        soap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP configuration for Moodle
RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/moodle.ini && \
    echo "upload_max_filesize=100M" >> /usr/local/etc/php/conf.d/moodle.ini && \
    echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/moodle.ini && \
    echo "max_input_vars=5000" >> /usr/local/etc/php/conf.d/moodle.ini && \
    echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/moodle.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/moodle.ini && \
    echo "zend.exception_ignore_args=1" >> /usr/local/etc/php/conf.d/moodle.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/moodle

# Copy PHP-FPM config
COPY php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy Moodle application files with correct ownership
COPY --chown=www-data:www-data moodle/ /var/www/moodle/

# Create and secure moodledata directory
RUN mkdir -p /var/www/moodledata \
    && chown www-data:www-data /var/www/moodledata \
    && chmod 750 /var/www/moodledata

# Create log directory and set proper permissions
RUN mkdir -p /var/log \
    && touch /var/log/php-fpm-access.log /var/log/php-fpm-slow.log \
    && chown www-data:www-data /var/log/*

# Set minimal permissions (fastest approach)
RUN chmod 755 /var/www/moodle

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

EXPOSE 9000
USER www-data
CMD ["php-fpm"]