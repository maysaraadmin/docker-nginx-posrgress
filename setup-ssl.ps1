# SSL Certificate Setup Script for Moodle (PowerShell version)
# This script sets up SSL certificates using Certbot

Write-Host "Starting SSL certificate setup for Moodle..."

# Create necessary directories
New-Item -ItemType Directory -Force -Path certbot\conf, certbot\www | Out-Null

# Start nginx in HTTP mode first to allow certbot challenge
Write-Host "Starting services..."
docker-compose up -d nginx postgres php redis

# Wait for services to be ready
Write-Host "Waiting for services to start..."
Start-Sleep -Seconds 10

# Generate SSL certificates using staging environment first
Write-Host "Generating SSL certificates (staging)..."
$stagingResult = docker-compose run --rm certbot certonly `
    --webroot `
    --webroot-path=/var/www/certbot `
    --email your-email@example.com `
    --agree-tos `
    --no-eff-email `
    --staging `
    -d localhost

# Check if staging worked
if ($LASTEXITCODE -eq 0) {
    Write-Host "Staging certificates generated successfully. Generating production certificates..."
    docker-compose run --rm certbot certonly `
        --webroot `
        --webroot-path=/var/www/certbot `
        --email your-email@example.com `
        --agree-tos `
        --no-eff-email `
        -d localhost
} else {
    Write-Host "Staging certificate generation failed. Please check your configuration."
    exit 1
}

# Restart nginx with SSL configuration
Write-Host "Restarting nginx with SSL configuration..."
docker-compose restart nginx

Write-Host "SSL setup complete!"
Write-Host "Your Moodle site should now be accessible at: https://localhost"
Write-Host "HTTP requests will be automatically redirected to HTTPS"
