#!/bin/bash

# Configuration Validation Script
# Validates required environment variables and secrets before starting services

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    case $status in
        "OK")
            echo -e "${GREEN}OK${NC}: $message"
            ;;
        "WARN")
            echo -e "${YELLOW}WARN${NC}: $message"
            ;;
        "ERROR")
            echo -e "${RED}ERROR${NC}: $message"
            ;;
    esac
}

# Function to validate environment variable
validate_env_var() {
    local var_name=$1
    local var_value=${!var_name}
    local default_value=$2
    
    if [ -z "$var_value" ]; then
        if [ -n "$default_value" ]; then
            print_status "WARN" "$var_name is not set, using default: $default_value"
            export $var_name="$default_value"
        else
            print_status "ERROR" "$var_name is not set and no default provided"
            return 1
        fi
    else
        print_status "OK" "$var_name is set to: $var_value"
    fi
}

# Function to validate secret file
validate_secret() {
    local secret_name=$1
    local secret_file=$2
    
    if [ -f "$secret_file" ]; then
        if [ -s "$secret_file" ]; then
            print_status "OK" "$secret_name secret file exists and is not empty"
        else
            print_status "ERROR" "$secret_name secret file is empty"
            return 1
        fi
    else
        print_status "ERROR" "$secret_name secret file does not exist: $secret_file"
        return 1
    fi
}

# Function to validate directory exists
validate_directory() {
    local dir_name=$1
    local dir_path=$2
    
    if [ -d "$dir_path" ]; then
        print_status "OK" "$dir_name directory exists: $dir_path"
    else
        print_status "WARN" "$dir_name directory does not exist, will be created: $dir_path"
        mkdir -p "$dir_path" || {
            print_status "ERROR" "Failed to create $dir_name directory: $dir_path"
            return 1
        }
    fi
}

echo "=== Docker Moodle Configuration Validation ==="
echo

# Validate environment variables
echo "Validating environment variables..."
validate_env_var "POSTGRES_DB" "moodle"
validate_env_var "PHP_MEMORY_LIMIT" "1G"
validate_env_var "PHP_MAX_EXECUTION_TIME_SECONDS" "300"
validate_env_var "SESSION_TIMEOUT_SECONDS" "7200"
validate_env_var "NGINX_RATE_LIMIT_REQUESTS_PER_SECOND" "10"
validate_env_var "PHP_MEMORY_LIMIT_RESERVATION" "512M"
validate_env_var "PHP_CPU_LIMIT" "0.5"
validate_env_var "POSTGRES_MEMORY_LIMIT" "1G"
validate_env_var "REDIS_MEMORY_LIMIT" "512M"
validate_env_var "CERTBOT_EMAIL" "admin@localhost"
validate_env_var "CERTBOT_DOMAIN" "localhost"

echo

# Validate secret files
echo "Validating secret files..."
validate_secret "PostgreSQL User" "./secrets/postgres_user.txt"
validate_secret "PostgreSQL Password" "./secrets/postgres_password.txt"
validate_secret "Redis Password" "./secrets/redis_password.txt"

echo

# Validate required directories
echo "Validating required directories..."
validate_directory "Backups" "./backups"
validate_directory "Certificates" "./certbot/conf"
validate_directory "Certificates Webroot" "./certbot/www"

echo

# Validate Docker Compose file
echo "Validating Docker Compose configuration..."
if command -v docker-compose >/dev/null 2>&1; then
    if docker-compose config >/dev/null 2>&1; then
        print_status "OK" "Docker Compose configuration is valid"
    else
        print_status "ERROR" "Docker Compose configuration is invalid"
        docker-compose config
        return 1
    fi
else
    print_status "WARN" "docker-compose command not found, skipping validation"
fi

echo

# Validate Nginx configuration
echo "Validating Nginx configuration..."
if [ -f "./nginx/conf.d/default.conf" ]; then
    if command -v nginx >/dev/null 2>&1; then
        if nginx -t -c ./nginx/nginx.conf >/dev/null 2>&1; then
            print_status "OK" "Nginx configuration is valid"
        else
            print_status "ERROR" "Nginx configuration is invalid"
            nginx -t -c ./nginx/nginx.conf
            return 1
        fi
    else
        print_status "WARN" "nginx command not found, skipping validation"
    fi
else
    print_status "ERROR" "Nginx configuration file not found"
    return 1
fi

echo

# Check for required system resources
echo "Checking system resources..."
if [ -f /proc/meminfo ]; then
    total_mem=$(grep MemTotal /proc/meminfo | awk '{print $2}')
    total_mem_gb=$((total_mem / 1024 / 1024))
    
    if [ $total_mem_gb -lt 4 ]; then
        print_status "WARN" "System has less than 4GB RAM ($total_mem_gb GB detected)"
    else
        print_status "OK" "System has sufficient RAM ($total_mem_gb GB)"
    fi
else
    print_status "WARN" "Cannot check system memory (not Linux)"
fi

echo

# Check Docker daemon
echo "Checking Docker daemon..."
if command -v docker >/dev/null 2>&1; then
    if docker info >/dev/null 2>&1; then
        print_status "OK" "Docker daemon is running"
    else
        print_status "ERROR" "Docker daemon is not accessible"
        return 1
    fi
else
    print_status "ERROR" "Docker command not found"
    return 1
fi

echo
echo "=== Validation Complete ==="
echo "All checks passed! The system is ready to start Docker Moodle."
echo
echo "To start the application, run:"
echo "  docker-compose up -d"
echo
echo "To check status, run:"
echo "  docker-compose ps"
echo
echo "To view logs, run:"
echo "  docker-compose logs -f"
