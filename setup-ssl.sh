#!/bin/bash

# SSL Certificate Setup Script for Moodle
# This script sets up SSL certificates using Certbot

echo "Starting SSL certificate setup for Moodle..."

# Create necessary directories
mkdir -p certbot/conf certbot/www

# Start nginx in HTTP mode first to allow certbot challenge
docker-compose up -d nginx postgres php redis

# Wait for services to be ready
echo "Waiting for services to start..."
sleep 10

# Generate SSL certificates using staging environment first
echo "Generating SSL certificates (staging)..."
docker-compose run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email your-email@example.com \
    --agree-tos \
    --no-eff-email \
    --staging \
    -d localhost

# If staging works, generate production certificates
if [ $? -eq 0 ]; then
    echo "Staging certificates generated successfully. Generating production certificates..."
    docker-compose run --rm certbot certonly \
        --webroot \
        --webroot-path=/var/www/certbot \
        --email your-email@example.com \
        --agree-tos \
        --no-eff-email \
        -d localhost
else
    echo "Staging certificate generation failed. Please check your configuration."
    exit 1
fi

# Restart nginx with SSL configuration
echo "Restarting nginx with SSL configuration..."
docker-compose restart nginx

echo "SSL setup complete!"
echo "Your Moodle site should now be accessible at: https://localhost"
echo "HTTP requests will be automatically redirected to HTTPS"
