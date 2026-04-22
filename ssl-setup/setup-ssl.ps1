# SSL Certificate Generation for Localhost (PowerShell)
# This script generates self-signed SSL certificates for local development

param(
    [Parameter(Mandatory=$false)]
    [string]$ConfigPath = "ssl-setup"
)

Write-Host "Generating SSL certificates for localhost..." -ForegroundColor Green

# Create directories
New-Item -ItemType Directory -Force -Path "$ConfigPath\certs"
New-Item -ItemType Directory -Force -Path "$ConfigPath\private"

try {
    # Generate private key
    Write-Host "Generating private key..." -ForegroundColor Yellow
    & openssl genrsa -out "$ConfigPath\private\localhost.key" 2048
    
    # Generate certificate signing request
    Write-Host "Generating certificate signing request..." -ForegroundColor Yellow
    & openssl req -new -key "$ConfigPath\private\localhost.key" `
        -out "$ConfigPath\localhost.csr" `
        -config "$ConfigPath\localhost.conf"
    
    # Generate self-signed certificate
    Write-Host "Generating self-signed certificate..." -ForegroundColor Yellow
    & openssl x509 -req -days 365 `
        -in "$ConfigPath\localhost.csr" `
        -signkey "$ConfigPath\private\localhost.key" `
        -out "$ConfigPath\certs\localhost.crt" `
        -extensions v3_req `
        -extfile "$ConfigPath\localhost.conf"
    
    # Set proper permissions
    Write-Host "Setting file permissions..." -ForegroundColor Yellow
    & icacls "$ConfigPath\private\localhost.key" /inheritance:r /grant:r "Authenticated Users:(R)"
    & icacls "$ConfigPath\certs\localhost.crt" /inheritance:r /grant:r "Authenticated Users:(R)"
    
    # Remove CSR
    Remove-Item "$ConfigPath\localhost.csr" -Force
    
    Write-Host "SSL certificates generated successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Files created:" -ForegroundColor Cyan
    Write-Host "  Private key: $ConfigPath\private\localhost.key" -ForegroundColor White
    Write-Host "  Certificate: $ConfigPath\certs\localhost.crt" -ForegroundColor White
    Write-Host ""
    
    # Display certificate details
    Write-Host "Certificate details:" -ForegroundColor Cyan
    & openssl x509 -in "$ConfigPath\certs\localhost.crt" -text -noout | Select-String "Subject Alternative Name" -Context 2,2
    
} catch {
    Write-Host "Error generating SSL certificates: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
