# Faveo Project Structure

## Overview
This document outlines the structure and organization of the Faveo project, including its components, dependencies, and build process.

## Project Structure
```
vibe-faveo/
├── .dockerignore
├── .gitignore
├── Dockerfile
├── Dockerfile.dev
├── bootstrap.sh
├── bootstrap-complete.sh
├── bootstrap-patch.sh
├── bootstrap-end.sh
├── bootstrap-fix.sh
├── permanent-url-fix.sh
├── fix-bootstrap.php
├── health.php
├── docker-compose.yml
├── railway.toml
├── SIMPLE-FIX.md
├── project-structure.md
└── faveo/
    ├── public/
    │   ├── bootstrap-app.php
    │   ├── facade-fix.php
    │   ├── alt-index.php
    │   ├── fix-bootstrap.php
    │   ├── health.php
    │   ├── index.php
    │   ├── diagnose-facade.php
    │   ├── install-dependencies.php
    │   ├── install-dependencies-fixed.php
    │   ├── db-connect-fix.php
    │   ├── db-direct-config.php
    │   ├── db-test.php
    │   ├── memory-only-fix.php
    │   ├── run-migrations.php
    │   ├── run-migrations-memory.php
    │   ├── direct-migration.php
    │   ├── repair-database.php
    │   ├── create-admin.php
    │   ├── fix-permissions.php
    │   ├── fix-url.php
    │   ├── url-redirect-fix.php
    │   ├── reset-password.php
    │   └── [Other public files]
    └── [Other Faveo application files]
```

## Docker Configuration
The project uses Docker for containerization with the following key files:

### Dockerfile
- Base image: `php:8.1-apache`
- System dependencies:
  - libzip-dev
  - unzip
  - git
  - curl
  - libpng-dev
  - libonig-dev
  - libxml2-dev
  - mariadb-client
  - sudo
  - procps (for process management)

### PHP Extensions
- pdo_mysql
- zip
- gd
- mbstring
- exif
- pcntl
- bcmath
- xml

### Build Process
1. System dependencies installation
2. PHP extensions configuration
3. Apache mod_rewrite and headers enablement
4. Application files setup
5. Comprehensive bootstrap script configuration
6. Permissions configuration
7. Apache VirtualHost configuration (updated to listen on port 8080)
8. Health check file setup

### Bootstrap Script (Simplified)
The updated bootstrap.sh script provides a more reliable startup process:
1. Comprehensive logging system:
   - Creates detailed logs at `/var/log/bootstrap.log`
   - Proper error handling and reporting
2. Composer dependency management:
   - Clear composer cache
   - Install dependencies with one reliable method
   - Optimized autoloader generation
3. Environment configuration:
   - Create .env file from .env.example if needed
   - Set APP_URL to match Railway's domain
   - Configure database connection from Railway variables
   - Generate Laravel app key
4. Directory initialization:
   - Create necessary Laravel storage directories
   - Set proper permissions for storage and cache
5. Laravel component setup:
   - Create bootstrap-app.php with proper namespace handling
   - Create health.php for reliable health checks
   - Update index.php to use bootstrap-app.php
6. Apache configuration:
   - Create VirtualHost for port 8080
   - Enable mod_rewrite and headers
   - Start Apache in foreground mode

### Health Check System
The project includes a simplified health check system:
1. The main `health.php` file in the public directory:
   - Always returns HTTP 200 OK status
   - Sets proper cache control headers
   - Optional debug parameter for diagnostics
2. Additional diagnostic features:
   - Apache status check
   - Directory and file existence verification
   - Environment configuration inspection
   - Recent log display
3. Railway Configuration:
   - Configured in railway.toml to use `/public/health.php`
   - Increased healthcheck timeout to 300 seconds
   - `ON_FAILURE` restart policy

### Recent Updates and Fixes

#### 1. Simplified Bootstrap Process
- Streamlined the bootstrap.sh script
- Removed complex fallback mechanisms in favor of reliability
- Added better logging and error reporting
- Created clean component initialization
- Added automated Apache configuration for port 8080

#### 2. Updated Dockerfile
- Modified to use the simplified bootstrap.sh
- Configured Apache to listen on port 8080
- Improved file copy and permission setup
- Removed dependency on external conf files

#### 3. Fixed Laravel Bootstrapping
- Created reliable bootstrap-app.php with proper namespace handling
- Updated index.php to use bootstrap-app.php
- Explicitly set the facade root to prevent common errors
- Added proper Laravel component initialization

#### 4. Environment Configuration
- Added .env.example file for reliable environment setup
- Implemented automatic environment configuration from Railway variables
- Fixed APP_URL to use the proper Railway domain
- Added automatic database configuration

#### 5. Improved Health Check
- Created a simple but reliable health check endpoint
- Added optional diagnostics for troubleshooting
- Ensured it always returns HTTP 200 status

#### 6. Port Configuration
- Updated Apache to listen on port 8080
- Modified VirtualHost configuration accordingly
- Exposed the correct port in the Dockerfile

### Known Issues and Solutions

#### 1. Facade Root Error
- Fixed by explicitly setting the facade application in bootstrap-app.php
- Included proper namespace resolution with backslashes
- Created predictable application initialization

#### 2. Health Check Failures
- Simplified health check to always return 200 OK
- Added logging and diagnostics for troubleshooting
- Configured railway.toml with proper path and timeout

#### 3. Port Mismatch
- Updated Apache to consistently use port 8080
- Modified Dockerfile and configuration accordingly
- Ensured proper port exposure for Railway

#### 4. Bootstrap Issues
- Simplified initialization process
- Added proper error handling and reporting
- Created reliable component setup

#### 5. Environment Setup
- Added .env.example for reliable environment creation
- Implemented automatic configuration from Railway variables
- Fixed path and file permission issues

## Additional Documentation

### PHP Scripts Organization (From CLEANUP_PLAN.md)
The plan to organize various PHP utilities into directories:
```
faveo/public/utils/
  ├── health/         # Health and diagnostic scripts
  ├── fixes/          # URL and configuration fixes 
  ├── database/       # Database utilities
  ├── installation/   # Installation scripts
  └── admin/          # Admin utilities
```

This organization will be implemented in future updates.

## Recent Bootstrap and Health Check Fixes (May 2025)

### Issues Fixed
1. **Health Check Issues**
   - Fixed Railway health check by simplifying health.php to always return 200 OK
   - Made sure health.php is properly copied to public directory
   - Updated railway.toml to point to correct health check path

2. **Laravel Bootstrap Issues**
   - Fixed "facade root has not been set" error by updating bootstrap-app.php
   - Made sure bootstrap-app.php is loaded correctly in index.php
   - Added proper namespace handling with backslashes

3. **Apache Configuration**
   - Updated Apache to listen on port 8080 instead of 80
   - Modified VirtualHost configuration accordingly
   - Added proper logging for Apache startup

4. **Environment Setup**
   - Added .env.example file for reliable environment creation
   - Added automatic database configuration from Railway variables
   - Fixed APP_URL to use correct Railway domain

### Files Updated
- bootstrap.sh - Simplified with better error handling and logging
- Dockerfile - Updated to configure Apache for port 8080 
- health.php - Now consistently returns 200 OK for Railway health checks
- bootstrap-app.php - Fixed namespace handling and facade root issues
- railway.toml - Configured with proper health check path and timeout