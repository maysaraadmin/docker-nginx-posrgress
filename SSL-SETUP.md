# SSL Certificate Setup for Moodle

This guide explains how to set up SSL certificates for your Moodle installation using Certbot.

## Prerequisites

1. **Domain Name**: You need a real domain name (not localhost) for SSL certificates
2. **DNS Configuration**: Your domain must point to your server's IP address
3. **Port 80/443**: Both ports must be accessible from the internet

## Quick Setup (PowerShell)

1. **Update Domain Name**:
   Edit `docker-compose.yml` and replace `localhost` with your actual domain:
   ```yaml
   command: certonly --webroot --webroot-path=/var/www/certbot --email your-email@example.com --agree-tos --no-eff-email -d yourdomain.com
   ```

2. **Run SSL Setup**:
   ```powershell
   .\setup-ssl.ps1
   ```

## Manual Setup Steps

### 1. Configure Domain
Edit these files to use your actual domain:

**docker-compose.yml** (certbot service):
```yaml
command: certonly --webroot --webroot-path=/var/www/certbot --email your-email@example.com --agree-tos --no-eff-email -d yourdomain.com
```

**nginx/conf.d/ssl.conf**:
```nginx
server_name yourdomain.com;
```

### 2. Generate Certificates

**For Testing (Staging)**:
```bash
docker-compose run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email your-email@example.com \
    --agree-tos \
    --no-eff-email \
    --staging \
    -d yourdomain.com
```

**For Production**:
```bash
docker-compose run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email your-email@example.com \
    --agree-tos \
    --no-eff-email \
    -d yourdomain.com
```

### 3. Start Services
```bash
docker-compose up -d
```

## SSL Configuration Features

The SSL configuration includes:

- **Modern SSL Protocols**: TLS 1.2 and 1.3
- **Strong Ciphers**: AES256-GCM with perfect forward secrecy
- **HSTS**: HTTP Strict Transport Security
- **Security Headers**: X-Frame-Options, XSS Protection, Content Security Policy
- **HTTP to HTTPS Redirect**: Automatic redirect from HTTP to HTTPS
- **OCSP Stapling**: Improved SSL performance

## Certificate Renewal

Let's Encrypt certificates expire every 90 days. Set up automatic renewal:

1. **Create renewal script**:
```bash
#!/bin/bash
docker-compose run --rm certbot renew
docker-compose restart nginx
```

2. **Set up cron job**:
```bash
0 2 * * * /path/to/renewal-script.sh
```

## Troubleshooting

### Certificate Generation Fails
- Check that port 80 is accessible from internet
- Verify DNS points to correct IP
- Ensure domain name is correctly configured

### Nginx SSL Errors
- Check certificate paths in `nginx/conf.d/ssl.conf`
- Verify certificates exist in `certbot/conf/live/yourdomain.com/`
- Check nginx logs: `docker-compose logs nginx`

### Browser Warnings
- Use staging certificates for testing only
- Production certificates require real domain names
- Clear browser cache and SSL state

## Files Created

- `nginx/conf.d/ssl.conf` - SSL-enabled nginx configuration
- `setup-ssl.ps1` - PowerShell setup script
- `setup-ssl.sh` - Bash setup script
- `certbot/conf/` - Certificate storage
- `certbot/www/` - Webroot for ACME challenges

## Security Notes

- SSL certificates are stored in `certbot/conf/` - protect this directory
- HTTP is automatically redirected to HTTPS
- Only strong SSL ciphers and protocols are enabled
- Security headers are configured for optimal protection
