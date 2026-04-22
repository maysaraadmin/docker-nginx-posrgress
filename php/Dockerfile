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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP configuration for Moodle - using environment variables
RUN { \
        echo "memory_limit=${PHP_MEMORY_LIMIT:-256M}"; \
        echo "upload_max_filesize=${UPLOAD_MAX_FILESIZE:-100M}"; \
        echo "post_max_size=${POST_MAX_SIZE:-100M}"; \
        echo "max_input_vars=5000"; \
        echo "max_execution_time=${PHP_MAX_EXECUTION_TIME:-300}"; \
        echo "max_input_time=${PHP_MAX_EXECUTION_TIME:-300}"; \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.enable_cli=1"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.interned_strings_buffer=8"; \
        echo "opcache.max_accelerated_files=4000"; \
        echo "opcache.revalidate_freq=2"; \
        echo "opcache.fast_shutdown=1"; \
        echo "zend.exception_ignore_args=1"; \
    } > /usr/local/etc/php/conf.d/moodle.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/moodle

# Copy PHP-FPM config
COPY php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy Moodle application files with correct ownership
COPY --chown=www-data:www-data moodle/ /var/www/moodle/

# Create directories and set permissions in single optimized RUN command
RUN mkdir -p /var/www/moodledata /var/log \
    && touch /var/log/php-fpm-access.log /var/log/php-fpm-slow.log \
    && chown www-data:www-data /var/www/moodledata /var/log /var/log/* \
    && chmod 750 /var/www/moodledata \
    && chmod 755 /var/www/moodle

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

EXPOSE 9000
USER www-data
CMD ["php-fpm"]