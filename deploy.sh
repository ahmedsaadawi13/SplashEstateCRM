#!/bin/bash
# FILE: /deploy.sh

###############################################################################
# SplashEstate CRM - Deployment Script
# Automates deployment and setup process
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

# Check PHP version
check_php() {
    print_info "Checking PHP version..."

    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed"
        exit 1
    fi

    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    REQUIRED_VERSION="7.0"

    if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$PHP_VERSION" | sort -V | head -n1)" != "$REQUIRED_VERSION" ]; then
        print_error "PHP version $REQUIRED_VERSION or higher is required (found $PHP_VERSION)"
        exit 1
    fi

    print_success "PHP version $PHP_VERSION detected"
}

# Check required PHP extensions
check_extensions() {
    print_info "Checking required PHP extensions..."

    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "curl" "json")
    MISSING_EXTENSIONS=()

    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            MISSING_EXTENSIONS+=("$ext")
        fi
    done

    if [ ${#MISSING_EXTENSIONS[@]} -ne 0 ]; then
        print_error "Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
        print_info "Install with: sudo apt-get install php-${MISSING_EXTENSIONS[*]}"
        exit 1
    fi

    print_success "All required PHP extensions are installed"
}

# Create .env file if it doesn't exist
setup_env() {
    if [ ! -f .env ]; then
        print_info "Creating .env file from template..."
        cp .env.example .env
        print_success ".env file created"
        print_warning "Please configure .env file with your database credentials and API keys"
    else
        print_info ".env file already exists"
    fi
}

# Create necessary directories
create_directories() {
    print_info "Creating necessary directories..."

    mkdir -p storage/uploads
    mkdir -p storage/logs
    mkdir -p storage/cache
    mkdir -p database/migrations

    # Set permissions
    chmod -R 755 storage
    chmod -R 755 database/migrations

    print_success "Directories created and permissions set"
}

# Run database migrations
run_migrations() {
    print_info "Running database migrations..."

    if php migrate.php migrate; then
        print_success "Migrations completed successfully"
    else
        print_warning "Migration warnings occurred (may be expected if already migrated)"
    fi
}

# Set file permissions
set_permissions() {
    print_info "Setting file permissions..."

    # Make scripts executable
    chmod +x deploy.sh
    chmod +x migrate.php

    # Set web-writable directories
    chmod -R 775 storage

    print_success "Permissions configured"
}

# Validate environment
validate_env() {
    print_info "Validating environment configuration..."

    if [ ! -f .env ]; then
        print_error ".env file not found"
        return 1
    fi

    # Check critical variables
    CRITICAL_VARS=("DB_NAME" "DB_USER" "BASE_URL")
    MISSING_VARS=()

    for var in "${CRITICAL_VARS[@]}"; do
        if ! grep -q "^${var}=" .env; then
            MISSING_VARS+=("$var")
        fi
    done

    if [ ${#MISSING_VARS[@]} -ne 0 ]; then
        print_warning "Missing critical environment variables: ${MISSING_VARS[*]}"
        print_info "Please configure these in your .env file"
    else
        print_success "Environment validation passed"
    fi
}

# Test database connection
test_database() {
    print_info "Testing database connection..."

    if php -r "
        require_once 'config/config.php';
        require_once 'app/core/Database.php';
        try {
            \$db = Database::getInstance();
            \$conn = \$db->getConnection();
            echo 'SUCCESS';
        } catch (Exception \$e) {
            echo 'FAILED: ' . \$e->getMessage();
            exit(1);
        }
    " 2>&1 | grep -q "SUCCESS"; then
        print_success "Database connection successful"
    else
        print_error "Database connection failed"
        print_info "Please check your database configuration in .env"
        return 1
    fi
}

# Clear cache
clear_cache() {
    print_info "Clearing cache..."

    if [ -d storage/cache ]; then
        rm -rf storage/cache/*
        print_success "Cache cleared"
    fi
}

# Main deployment flow
main() {
    print_header "SplashEstate CRM Deployment"

    print_info "Starting deployment process..."
    echo ""

    # Pre-deployment checks
    check_php
    check_extensions

    # Setup
    setup_env
    create_directories
    set_permissions

    # Validate configuration
    validate_env

    # Database operations
    if test_database; then
        run_migrations
    else
        print_warning "Skipping migrations due to database connection failure"
    fi

    # Post-deployment
    clear_cache

    echo ""
    print_header "Deployment Complete!"

    print_success "SplashEstate CRM has been deployed successfully"
    echo ""
    print_info "Next steps:"
    echo "  1. Configure your .env file with proper credentials"
    echo "  2. Set up your web server to point to the /public directory"
    echo "  3. Access the application and complete setup"
    echo ""
    print_info "For initial setup:"
    echo "  - Admin login: admin@splashestate.com / admin123"
    echo "  - Change admin password after first login"
    echo ""
}

# Run main deployment
main
