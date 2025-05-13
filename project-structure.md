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
   - Create a health check endpoint for Railway (`/public/health.php`)
4. Environment detection and configuration:
   - Detect Railway environment via `RAILWAY_ENVIRONMENT` variable
   - Configure database using Railway environment variables
   - Configure app URL based on Railway settings
   - Set Apache ServerName to suppress warning messages
   - Adjust Apache to use the correct PORT as specified by Railway
   - Fall back to local configuration if not in Railway environment
5. Start Apache server

### Known Issues
The bootstrap script handles the following errors:
- Facade root errors during artisan commands (bypassed using PHP direct file operations)
- Ambiguous class resolution warnings from Faveo codebase (these are expected)
- Apache server name warning (resolved by setting ServerName directive)
- Missing frontend asset files (now handled via webpack.mix.js improvements)

### Frontend Asset Management
The `webpack.mix.js` file includes the following improvements:
- Automatic creation of missing resources directories
- Creation of placeholder JS and CSS files if not present
- Use of relative paths to prevent build failures
- Disabled URL processing for CSS to reduce build errors

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
- APP_URL: Dynamically set based on environment
- Database configuration (MySQL):
  - DB_HOST: Used from environment variables in Railway, defaults to 'db' locally
  - DB_DATABASE: Used from environment variables in Railway, defaults to 'faveo' locally
  - DB_USERNAME: Used from environment variables in Railway, defaults to 'faveo' locally
  - DB_PASSWORD: Used from environment variables in Railway, defaults to 'faveo_password' locally
- Mail configuration
- FCM configuration

## Railway Configuration
The application is configured for deployment on Railway with the `railway.toml` file:
- **Build Configuration**:
  - Uses the Dockerfile for building
- **Deployment Configuration**:
  - Start command: `/usr/local/bin/bootstrap.sh`
  - Health check path: `/public/health.php`
  - Health check timeout: 100 seconds
  - Restart policy: ON_FAILURE with max 10 retries
- **Setup Phase**:
  - Required Nix packages: php82, php82Packages.composer
- **Database Configuration**:
  - Uses Railway's MySQL plugin environment variables:
    - MYSQLHOST: Host name for the database (default: mysql.railway.internal)
    - MYSQLPORT: Port for the database (default: 3306)
    - MYSQLDATABASE: Database name (default: railway)
    - MYSQLUSER: Database username (default: root)
    - MYSQLPASSWORD: Database password

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

6. Railway Deployment Failures
   - Issue: Database configuration not adapting to Railway environment
   - Solution: Added environment variable detection and dynamic configuration
   
7. Railway Health Check Failures
   - Issue: Health check configured incorrectly causing deployment failures
   - Solution: Updated railway.toml to use `/public/health.php` as the health check path and set the correct start command
   
8. Apache Server Name Warning
   - Issue: Warning messages about server name not being set
   - Solution: Added ServerName directive configuration in the bootstrap script
   
9. Asset Compilation Errors
   - Issue: Missing CSS and JS files causing build failures
   - Solution: Modified webpack.mix.js to create placeholder files and use relative paths

### Build Process Improvements
1. Created a bootstrap script for runtime initialization
2. Used direct file operations instead of artisan commands for cache clearing
3. Added pre-generated application key to avoid key generation issues
4. Created required storage directories explicitly
5. Added Railway environment detection and configuration
6. Improved webpack configuration to handle missing assets
7. Added health check endpoint for monitoring
8. Set Apache ServerName configuration to suppress warnings
9. Updated railway.toml to use the bootstrap script as the start command

## Development Guidelines
1. Always run `composer update` after modifying composer.json
2. Ensure proper permissions on storage and cache directories
3. Clear config and cache when encountering facade-related issues
4. Use Docker Compose for local development environment
5. Be aware of the ambiguous class resolution warnings (they're expected)
6. Test your changes locally before deploying to Railway

## Deployment
The project is configured for deployment on Railway with the following considerations:
- Environment variables must be properly set in the Railway dashboard:
  - DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD (for database connection)
  - APP_URL (for application URL)
  - PORT (automatically provided by Railway)
- The health check path is set to `/public/health.php` in railway.toml
- The start command is set to `/usr/local/bin/bootstrap.sh` to ensure proper initialization
- The bootstrap script automatically configures Apache to use the correct port
- If you encounter persistent issues, check Railway logs for specific error messages

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
4. Environment issues: Verify .env configuration matches expected environment variables
5. Docker build issues: Use `docker-compose down` followed by `docker-compose up --build`
6. Laravel facade errors: Expected in Faveo application - these can be bypassed using direct file operations
7. Railway deployment issues:
   - Verify health check is configured to use `/public/health.php` in railway.toml
   - Ensure start command is set to `/usr/local/bin/bootstrap.sh` in railway.toml
   - Verify database credentials are set correctly in Railway environment variables
   - Check logs for any container startup errors
   - Ensure the PORT environment variable is being respected by your container
8. Apache server name warnings: These are now suppressed by setting ServerName directive in the bootstrap script
9. Database connection issues in Railway:
   - Ensure you have added a MySQL plugin in the Railway dashboard
   - Make sure the bootstrap script is using the correct environment variables for database connection:
     - MYSQLHOST instead of DB_HOST
     - MYSQLPORT instead of DB_PORT
     - MYSQLDATABASE instead of DB_DATABASE
     - MYSQLUSER instead of DB_USERNAME
     - MYSQLPASSWORD instead of DB_PASSWORD
   - Use /public/db-test.php to diagnose database connection issues

## Future Improvements
1. Automated dependency updates
2. Enhanced error handling
3. Improved build process
4. Better documentation
5. Address class ambiguity warnings through proper namespace management
6. Create a dedicated Railway configuration section in the bootstrap script
7. Implement proper Laravel Mix asset compilation 