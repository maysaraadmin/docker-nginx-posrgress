#!/bin/bash

# SSL Certificate Generation for Localhost
# This script generates self-signed SSL certificates for local development

set -e

echo "Generating SSL certificates for localhost..."

# Create directories
mkdir -p ssl-setup/certs ssl-setup/private

# Generate private key
echo "Generating private key..."
openssl genrsa -out ssl-setup/private/localhost.key 2048

# Generate certificate signing request
echo "Generating certificate signing request..."
openssl req -new -key ssl-setup/private/localhost.key \
    -out ssl-setup/localhost.csr \
    -config ssl-setup/localhost.conf

# Generate self-signed certificate
echo "Generating self-signed certificate..."
openssl x509 -req -days 365 \
    -in ssl-setup/localhost.csr \
    -signkey ssl-setup/private/localhost.key \
    -out ssl-setup/certs/localhost.crt \
    -extensions v3_req \
    -extfile ssl-setup/localhost.conf

# Set proper permissions
chmod 600 ssl-setup/private/localhost.key
chmod 644 ssl-setup/certs/localhost.crt

# Remove CSR
rm ssl-setup/localhost.csr

echo "SSL certificates generated successfully!"
echo ""
echo "Files created:"
echo "  Private key: ssl-setup/private/localhost.key"
echo "  Certificate: ssl-setup/certs/localhost.crt"
echo ""
echo "Certificate details:"
openssl x509 -in ssl-setup/certs/localhost.crt -text -noout | grep -A 2 "Subject Alternative Name"
