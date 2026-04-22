# SSL Setup for Localhost

Complete SSL certificate setup for local development with self-signed certificates and HTTPS configuration.

## Overview

This directory contains everything needed to enable HTTPS for your local Moodle development environment:

- **Self-signed certificates** for localhost
- **Nginx SSL configuration** with security headers
- **Automated scripts** for certificate generation
- **Browser trust instructions** for local development

## Files

```
ssl-setup/
├── 📄 localhost.conf        # OpenSSL configuration
├── 📄 generate-ssl.sh       # Bash script for certificate generation
├── 📄 setup-ssl.ps1         # PowerShell script for certificate generation
├── 📁 certs/                 # Generated certificates (auto-created)
└── 📁 private/               # Private keys (auto-created)
```

## Quick Start

### Option 1: Using Bash Script (Linux/Mac)

1. **Generate certificates:**
```bash
cd ssl-setup
chmod +x generate-ssl.sh
./generate-ssl.sh
```

2. **Update Docker Compose:**
```bash
# Restart services to apply SSL configuration
docker-compose down
docker-compose up -d
```

3. **Access Moodle:**
```
https://localhost
```

### Option 2: Using PowerShell Script (Windows)

1. **Generate certificates:**
```powershell
cd ssl-setup
.\setup-ssl.ps1
```

2. **Update Docker Compose:**
```powershell
docker-compose down
docker-compose up -d
```

3. **Access Moodle:**
```
https://localhost
```

### Option 3: Manual Generation

1. **Generate private key:**
```bash
openssl genrsa -out ssl-setup/private/localhost.key 2048
```

2. **Generate certificate:**
```bash
openssl req -new -x509 -key ssl-setup/private/localhost.key \
    -out ssl-setup/certs/localhost.crt \
    -days 365 \
    -subj "/CN=localhost"
```

## Certificate Configuration

### OpenSSL Configuration (localhost.conf)
- **Subject**: localhost
- **Subject Alternative Names**: 
  - DNS: localhost, *.localhost
  - IP: 127.0.0.1, ::1
- **Key Usage**: Server authentication
- **Extended Key Usage**: Server authentication

### Generated Certificates
- **Private Key**: `ssl-setup/private/localhost.key` (2048-bit RSA)
- **Certificate**: `ssl-setup/certs/localhost.crt` (1-year validity)
- **Permissions**: Secure file permissions set automatically

## Nginx SSL Configuration

The SSL configuration in `nginx/conf.d/ssl-localhost.conf` includes:

### Security Features
- **TLS Protocols**: TLS 1.2, 1.3 only
- **Strong Ciphers**: Modern cipher suites
- **HSTS**: Strict Transport Security
- **Security Headers**: CSP, XSS protection, frame options

### Performance Features
- **HTTP/2 Support**: Enabled
- **Session Caching**: 10-minute SSL sessions
- **Gzip Compression**: Enabled for static assets
- **Browser Caching**: 1-year cache for static files

### Moodle Integration
- **PHP Processing**: FastCGI with 600s timeout
- **File Uploads**: 100MB max file size
- **Security**: Hidden sensitive files
- **Health Checks**: `/health` endpoint

## Browser Trust Setup

### Chrome/Edge
1. Navigate to `https://localhost`
2. Click "Advanced" → "Proceed to localhost (unsafe)"
3. Certificate will be trusted for the session

### Firefox
1. Navigate to `https://localhost`
2. Click "Advanced" → "Accept the Risk and Continue"
3. Certificate will be trusted for the session

### Safari
1. Navigate to `https://localhost`
2. Click "Show Details" → "Visit this website"
3. Certificate will be trusted for the session

### System-wide Trust (Optional)

#### macOS
```bash
# Add certificate to system keychain
sudo security add-trusted-cert -d -r trustRoot -k /etc/ssl/certs/localhost.crt

# Update certificate trust settings
sudo security add-trusted-cert -d -r trustAsRoot -k /etc/ssl/certs/localhost.crt
```

#### Windows
```powershell
# Import certificate to Trusted Root Certification Authorities
certlm.msc
# Navigate to: Trusted Root Certification Authorities → Certificates → Import
# Select: ssl-setup/certs/localhost.crt
```

#### Linux
```bash
# Add to system certificates (Ubuntu/Debian)
sudo cp ssl-setup/certs/localhost.crt /usr/local/share/ca-certificates/
sudo update-ca-certificates

# Add to system certificates (CentOS/RHEL)
sudo cp ssl-setup/certs/localhost.crt /etc/pki/ca-trust/source/anchors/
sudo update-ca-trust extract
```

## Docker Integration

### Volume Mounts
The Docker Compose configuration automatically mounts:
- `ssl-setup/certs` → `/etc/ssl/certs` (certificates)
- `ssl-setup/private` → `/etc/ssl/private` (private keys)

### Port Configuration
- **HTTP**: Port 80 (redirects to HTTPS)
- **HTTPS**: Port 443 (SSL termination)

### Service Dependencies
- Nginx depends on PHP service
- SSL certificates mounted before Nginx starts
- Automatic HTTPS redirect from HTTP to HTTPS

## Security Considerations

### Certificate Security
- **Key Size**: 2048-bit RSA (minimum recommended)
- **Validity**: 1 year (renew annually)
- **File Permissions**: 600 for private key, 644 for certificate
- **SAN Support**: Multiple domain names and IPs

### Web Server Security
- **TLS 1.2+**: No legacy protocols
- **Strong Ciphers**: No weak ciphers
- **HSTS**: Enforce HTTPS for 1 year
- **CSP**: Prevent XSS attacks

### Development vs Production
- **Self-signed**: Only for development
- **Let's Encrypt**: Use for production
- **Domain**: Replace localhost with real domain
- **Certificate Authority**: Use trusted CA for production

## Troubleshooting

### Certificate Issues
```bash
# Check certificate validity
openssl x509 -in ssl-setup/certs/localhost.crt -text -noout

# Verify certificate matches key
openssl x509 -noout -modulus -in ssl-setup/certs/localhost.crt | openssl md5
openssl rsa -noout -modulus -in ssl-setup/private/localhost.key | openssl md5
```

### Nginx Issues
```bash
# Test Nginx configuration
docker-compose exec nginx nginx -t

# Check Nginx logs
docker-compose logs nginx

# Verify SSL certificate is loaded
docker-compose exec nginx ls -la /etc/ssl/certs/
docker-compose exec nginx ls -la /etc/ssl/private/
```

### Browser Issues
- **Clear cache**: Remove browser cache and cookies
- **Incognito mode**: Try in private browsing
- **Different browser**: Test with another browser
- **System trust**: Add certificate to system trust store

### Docker Issues
```bash
# Rebuild containers with SSL
docker-compose down
docker-compose build --no-cache nginx
docker-compose up -d

# Check volume mounts
docker-compose exec nginx df -h

# Test SSL connectivity
openssl s_client -connect localhost:443 -servername localhost
```

## Production Deployment

### Let's Encrypt Integration
For production deployment, replace self-signed certificates with Let's Encrypt:

1. **Update domain** in nginx configuration
2. **Configure certbot** for automatic renewal
3. **Update Docker Compose** volumes for Let's Encrypt
4. **Set up monitoring** for certificate expiration

### Certificate Automation
```bash
# Automatic renewal (cron job)
0 0,12 * * * /usr/bin/certbot renew --quiet

# Test renewal process
/usr/bin/certbot renew --dry-run
```

## Support

### Common Issues
- **Certificate not trusted**: Use browser trust setup
- **Mixed content**: Ensure all resources use HTTPS
- **HSTS errors**: Clear browser cache
- **Certificate expired**: Regenerate certificates

### Debug Commands
```bash
# Check certificate details
openssl x509 -in ssl-setup/certs/localhost.crt -text -noout

# Test SSL connection
curl -v https://localhost

# Check Nginx SSL status
docker-compose exec nginx nginx -T | grep -A 10 ssl_
```

## License

This SSL setup is part of the main project and follows the same MIT License.
