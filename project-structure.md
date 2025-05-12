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
├── docker-compose.yml
├── railway.toml
├── project-structure.md
└── faveo/
    └── [Faveo application files]
```

## Docker Configuration
The project uses Docker for containerization with the following key files:

### Dockerfile
- Base image: `php:8.2-apache`
- System dependencies:
  - libzip-dev
  - unzip
  - git
  - curl
  - npm
  - libpng-dev
  - libonig-dev
  - libxml2-dev
  - libc-client-dev
  - libkrb5-dev

### PHP Extensions
- pdo_mysql
- zip
- gd
- mbstring
- exif
- pcntl
- bcmath
- xml
- imap

### Build Process
1. System dependencies installation
2. PHP extensions configuration
3. Apache mod_rewrite enablement
4. Application files setup
5. Composer installation
6. Permissions configuration
7. Environment setup
8. Create bootstrap script for runtime initialization
9. Node dependencies installation
10. Final permissions setup

### Bootstrap Script
The application uses a bootstrap script (`bootstrap.sh`) that runs at container startup:
1. Composer dependency management:
   - Clear composer cache
   - Install dependencies with error handling
   - Generate optimized autoloader with error handling
2. Directory initialization:
   - Create necessary Laravel storage directories (cache/data, sessions, views, app/public)
   - Set proper permissions for storage and cache
3. Laravel initialization:
   - Create .env file if not exists (from .env.example)
   - Ensure APP_KEY is set in .env file
   - Clear Laravel caches by directly removing cache files
   - Set database configuration directly in .env file using sed
4. Start Apache server

### Known Issues
The bootstrap script handles the following errors:
- Facade root errors during artisan commands (bypassed using PHP direct file operations)
- Ambiguous class resolution warnings from Faveo codebase (these are expected)
- Apache server name warning (cosmetic issue only)

### Docker Compose
The `docker-compose.yml` file (without version attribute) defines two services:
- **faveo**: The main application container
  - Built from the local Dockerfile
  - Mapped to port 8080
  - Connected to the MySQL database
- **db**: MySQL 8.0 database
  - Persistent volume for data storage
  - Preconfigured with database name, user, and password

## Dependencies
### Required Packages
- laravel/sanctum
- diglactic/laravel-breadcrumbs
- laminas/laminas-escaper
- laminas/laminas-http
- laminas/laminas-hydrator
- laminas/laminas-json (abandoned)
- laminas/laminas-loader (abandoned)
- laminas/laminas-stdlib
- laminas/laminas-uri
- laminas/laminas-validator

### Development Dependencies
- laravel/sail

## Environment Configuration
The application uses a `.env` file with the following key configurations:
- APP_NAME=Faveo
- APP_ENV=local
- APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew= (pre-generated key)
- APP_DEBUG=true
- APP_URL=http://localhost:8080
- Database configuration (MySQL)
  - DB_HOST=db (matches service name in docker-compose)
  - DB_DATABASE=faveo
  - DB_USERNAME=faveo
  - DB_PASSWORD=faveo_password
- Mail configuration
- FCM configuration

## Build Issues and Solutions
### Known Issues
1. Composer Dependencies
   - Issue: Lock file out of sync with composer.json
   - Solution: Added `composer clearcache` and updated the dependency installation process

2. Laravel Key Generation
   - Issue: Facade root not set
   - Solution: Moved Laravel initialization to direct file operations instead of artisan commands

3. Docker Compose Version
   - Issue: Obsolete version attribute warning
   - Solution: Removed version attribute from docker-compose.yml

4. Application Environment
   - Issue: Laravel initialization failing in production mode
   - Solution: Changed to local environment with debug enabled for development

5. Class Resolution Ambiguity
   - Issue: Multiple classes with same name in different locations
   - Solution: These are expected warnings in the Faveo codebase and don't affect functionality

### Build Process Improvements
1. Created a bootstrap script for runtime initialization
2. Used direct file operations instead of artisan commands for cache clearing
3. Added pre-generated application key to avoid key generation issues
4. Created required storage directories explicitly
5. Set database configuration using sed directly in bootstrap script

## Development Guidelines
1. Always run `composer update` after modifying composer.json
2. Ensure proper permissions on storage and cache directories
3. Clear config and cache when encountering facade-related issues
4. Use Docker Compose for local development environment
5. Be aware of the ambiguous class resolution warnings (they're expected)

## Deployment
The project is configured for deployment on Railway with the following considerations:
- Environment variables must be properly set
- Database migrations must be run
- Storage permissions must be configured
- Cache must be cleared after deployment

## Testing
- Unit tests should be run before deployment
- Integration tests for critical paths
- Environment-specific test configurations

## Maintenance
Regular maintenance tasks:
1. Update dependencies
2. Clear caches
3. Check storage permissions
4. Verify environment configurations

## Security Considerations
1. Environment variables protection
2. File permissions management
3. Dependency security updates
4. API key management

## Performance Optimization
1. Composer autoload optimization
2. Asset compilation
3. Cache configuration
4. Database optimization

## Troubleshooting
Common issues and solutions:
1. Dependency conflicts: Run `composer clearcache` followed by `composer update`
2. Permission issues: Check directory permissions for storage and bootstrap/cache
3. Cache issues: Use direct file operations to clear Laravel cache files
4. Environment issues: Verify .env configuration matches docker-compose environment variables
5. Docker build issues: Use `docker-compose down` followed by `docker-compose up --build`
6. Laravel facade errors: Expected in Faveo application - these can be bypassed using direct file operations

## Future Improvements
1. Automated dependency updates
2. Enhanced error handling
3. Improved build process
4. Better documentation
5. Address class ambiguity warnings through proper namespace management 