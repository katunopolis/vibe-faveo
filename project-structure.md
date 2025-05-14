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
├── bootstrap-patch.sh           # URL redirect fix patch for bootstrap.sh
├── permanent-url-fix.sh         # Standalone URL redirect fix script
├── docker-compose.yml
├── railway.toml
├── project-structure.md
└── faveo/
    ├── public/
    │   ├── bootstrap-app.php                # Laravel bootstrapper fix
    │   ├── facade-fix.php                   # Facade root issue fix
    │   ├── alt-index.php                    # Alternative entry point
    │   ├── fix-bootstrap.php                # Bootstrap fixer utility
    │   ├── diagnose-facade.php              # Facade diagnostic tool
    │   ├── install-dependencies.php         # Composer dependency installer
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
    │   ├── url-redirect-fix.php             # Original PHP-based URL fix (fallback)
    │   └── [Other public files]
    └── [Other Faveo application files]
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
8. Copy bootstrap script for runtime initialization
9. Node dependencies installation
10. Final permissions setup

### Bootstrap Script
The application uses a bootstrap script (`bootstrap.sh`) that runs at container startup:
1. Composer dependency management:
   - Clear composer cache
   - Install dependencies with multiple fallback options:
     - First attempt: `--no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction`
     - Second attempt: `--no-dev --no-plugins --prefer-dist --no-progress --no-interaction`
     - Third attempt: `--no-dev --no-interaction`
   - Creates a flag file (`needs_composer_install`) if all installation attempts fail
   - Generate optimized autoloader with error handling
2. Directory initialization:
   - Create necessary Laravel storage directories (cache/data, sessions, views, app/public)
   - Set proper permissions for storage and cache
3. Laravel initialization:
   - Create .env file if not exists (from .env.example)
   - Create a health check endpoint for Railway (`/public/health.php`)
   - Create diagnostic scripts for database connection troubleshooting
4. Database connection configuration:
   - Auto-detect available MySQL connections (both internal and external)
   - Try multiple connection methods (internal hostname, external hostname, socket)
   - Use the first successful connection method
   - Create a smart failover system between connection methods
   - Generate configuration files based on the working connection
5. Environment detection and configuration:
   - Detect Railway environment via `RAILWAY_ENVIRONMENT` variable
   - Extract connection details from MySQL_PUBLIC_URL if available
   - Configure database using Railway environment variables
   - Configure app URL based on Railway settings
   - Set Apache ServerName to suppress warning messages
   - Adjust Apache to use the correct PORT as specified by Railway
   - Fall back to local configuration if not in Railway environment
6. URL redirect fix:
   - Detect the correct application URL
   - Update database settings with the correct URL
   - Update the .env file with the correct APP_URL
   - Create a config override file with the correct URL
   - Update bootstrap file to include URL overrides
   - Patch index.php to include the bootstrap file
   - Clear Laravel caches
7. Start Apache server

### Known Issues
The bootstrap script handles the following errors:
- Facade root errors during artisan commands (bypassed using PHP direct file operations)
- Ambiguous class resolution warnings from Faveo codebase (these are expected)
- Apache server name warning (resolved by setting ServerName directive)
- Missing frontend asset files (now handled via webpack.mix.js improvements)
- MySQL hostname resolution issues (resolved using external hostname and port)
- URL redirect issues (application redirecting to port 8080)

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
  - DB_HOST: Auto-detected working hostname (internal or external)
  - DB_PORT: Auto-detected working port
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
- **Builder Environment**:
  - Required Nix packages: nodejs, yarn
- **Database Configuration**:
  - Uses Railway's MySQL plugin environment variables:
    - MYSQLHOST: Host name for the database (internal hostname)
    - MYSQLPORT: Port for the database (default: 3306)
    - MYSQLDATABASE: Database name (default: railway)
    - MYSQLUSER: Database username
    - MYSQLPASSWORD: Database password
    - MYSQL_PUBLIC_URL: External URL for MySQL (format: mysql://user:pass@hostname:port/database)
  - **IMPORTANT**: You must link the MySQL service to your app service in the Railway dashboard
  - The bootstrap script will automatically:
    - Parse both internal and external connection details
    - Test all possible connection methods
    - Use the first successful connection
    - Configure the application with working connection details

## URL Redirect Fix
The project includes a permanent solution for the URL redirect issues in Faveo, by integrating the fix directly into the bootstrap process.

### Problem Background
The Faveo application on Railway has been experiencing URL redirect issues where:
1. The application redirects from `https://vibe-faveo-production.up.railway.app/` to `https://vibe-faveo-production.up.railway.app:8080/public/`
2. This occurs because the URL in the database is set to "http://localhost" or includes port 8080
3. Previous fix attempts used PHP scripts that faced permission issues:
   - `Warning: file_put_contents(/var/www/html/.env): Failed to open stream: Permission denied`
   - `Warning: file_put_contents(/var/www/html/public/db_bootstrap.php): Failed to open stream: Permission denied`

### Implementation Options

#### Option 1: Direct Modification of bootstrap.sh
1. Edit the bootstrap.sh file in your repository
2. Add the contents of bootstrap-patch.sh right before the `apache2-foreground` command (at the end of the file)
3. Commit and push the changes to GitHub
4. Railway will automatically rebuild and deploy

#### Option 2: Separate Script Approach
1. Use the permanent-url-fix.sh file in your repository
2. Modify your Dockerfile to include:
   ```dockerfile
   COPY permanent-url-fix.sh /usr/local/bin/permanent-url-fix.sh
   RUN chmod +x /usr/local/bin/permanent-url-fix.sh
   ```
3. Modify bootstrap.sh to include before the `apache2-foreground` line:
   ```bash
   # Fix URL redirects
   source /usr/local/bin/permanent-url-fix.sh
   fix_url_redirect
   ```
4. Commit and push the changes to GitHub
5. Railway will automatically rebuild and deploy

### Benefits
- Runs with root privileges during container startup, avoiding permission issues
- Applies automatically on every deployment without manual intervention
- Makes changes once at startup, not repeatedly
- Uses the same fix logic as the PHP script but at a more appropriate time
- Eliminates the tedious deploy-and-check cycle for URL fixes

### Fallback Method
If this fix doesn't work for any reason, the original `url-redirect-fix.php` script will still be available as a fallback method.

## Facade Root Error Fix
The application includes several specialized scripts to deal with the common Laravel "facade root has not been set" error:

### bootstrap-app.php
- A custom Laravel bootstrapper script that fixes common bootstrapping issues
- Should be included at the beginning of index.php
- Sets up essential environment variables for database connection
- Creates required storage directories if missing
- Clears cached configurations that might be causing issues
- Explicitly creates an application instance and sets it as the facade root
- Can be accessed directly to view configuration status and patch index.php

### facade-fix.php
- A direct facade root fixer script that initializes the Laravel application
- Can be included in any PHP file that needs to use Laravel facades
- Creates a minimal application instance and sets it as the facade root
- Initializes essential facades like App and Config
- Implements proper error handling and prevents recursion
- Can be accessed directly to see usage instructions

### alt-index.php
- An alternative entry point to the application that bypasses the main index.php
- Uses facade-fix.php to ensure proper bootstrapping
- Includes comprehensive error handling to display useful diagnostics
- Provides links to other diagnostic tools when errors occur
- Useful for testing when the main entry point is failing

### fix-bootstrap.php
- A comprehensive bootstrap fixing utility that addresses various bootstrapping issues
- Patches index.php to include bootstrap-app.php if needed
- Creates and verifies critical directories (storage, bootstrap/cache)
- Fixes permissions on key directories and files
- Shows detailed success and error messages
- Provides next steps and links to other diagnostic tools

### diagnose-facade.php
- A diagnostic tool specifically for facade root issues
- Checks for the `needs_composer_install` flag and displays a prominent message
- Provides a direct link to the dependency installer when Laravel classes are missing
- Tests Laravel framework loading
- Tests bootstrap file integrity
- Tests facade initialization
- Tests storage and cache directory permissions
- Tests environment configuration
- Automatically applies fixes when possible
- Provides detailed technical explanations and next steps

## Diagnostic Tools
The application includes several diagnostic PHP scripts to help troubleshoot connection issues:

### install-dependencies.php
- Web interface to install Composer dependencies when needed
- Shows current status of vendor directory and autoloader
- Runs composer commands with proper options to optimize installation
- Includes authentication to prevent unauthorized use
- Creates an optimized autoloader to improve performance
- Displays detailed output from composer commands
- Automatically executes multiple dependency management steps:
  1. Clearing composer cache
  2. Installing dependencies without dev packages
  3. Generating an optimized autoloader
  4. Optionally clearing Laravel config cache
- Removes the `needs_composer_install` flag once installation completes successfully
- Links to other diagnostic tools for further setup steps

### db-test.php
- Tests database connection using environment variables
- Shows detailed connection error information
- Performs hostname resolution tests
- Tries alternative hostnames and connection methods

### db-connect-fix.php
- Comprehensive tool to fix database connection issues
- Tests multiple hostname/port combinations
- Tries different connection methods (PDO, mysqli, socket)
- Automatically implements a working solution if found
- Updates configuration files with working connection details

### db-direct-config.php
- Directly configures database connection without relying on .env
- Creates bootstrap file to be included at the beginning of index.php
- Updates Laravel database configuration to use environment variables
- Tests connection with the configured settings

### health.php
- Simple health check endpoint for Railway
- Returns "OK" to indicate the application is running

### run-migrations.php
- Comprehensive script for setting up the Faveo database
- Runs all necessary migrations and seeds
- Creates required database structure
- Shows detailed output of each command with error handling
- Verifies database tables after migration

### repair-database.php
- Diagnostic and repair tool for database structure issues
- Checks for required Faveo tables and creates missing ones
- Verifies table structure and attempts to fix issues
- Can reset and recreate the database if necessary
- Shows comprehensive table listing

### create-admin.php
- User-friendly interface for creating an admin user
- Tests database connectivity before showing the form
- Can create a new admin or update an existing user account
- Provides secure password hashing
- Displays login credentials for immediate access

### fix-permissions.php
- Fixes common permission issues in Faveo installation
- Shows detailed file system information
- Sets proper ownership and permissions on critical directories
- Creates missing directories in the Laravel storage structure
- Clears application caches

### url-redirect-fix.php
- Fixes URL redirection issues (fallback method)
- Updates URL settings in database
- Attempts to update .env file with correct APP_URL
- Creates config override file for app.url
- Clears Laravel caches
- Provides comprehensive diagnostic information
- Shows current URL configurations
- Authentication protected

## Database Initialization
The project includes a complete set of database initialization and maintenance tools:

### Database Setup Process
1. Run `/public/run-migrations.php` to create database tables and run initial migrations
2. Run `/public/repair-database.php` to verify table structures and fix any issues
3. Run `/public/create-admin.php` to create an administrator account
4. Run `/public/fix-permissions.php` to ensure proper file permissions

### Features
- **Streamlined Setup**: Complete database setup through web interface without command line access
- **Diagnostic Feedback**: Clear visual feedback on each step of the setup process
- **Error Recovery**: Automatic attempts to fix common database and file permission issues
- **User Management**: Simple admin user creation with secure password handling
- **Self-guided Process**: Each script includes navigation links to the next steps

### Technical Details
- Scripts use PDO for database connections with proper error handling
- Laravel artisan commands are executed through PHP's exec() function
- Successful connection prioritizes internal Railway networking (mysql.railway.internal)
- Fallback mechanisms ensure reliability across different deployment environments
- File permission issues are automatically detected and corrected

## Build Issues and Solutions
### Known Issues
1. Composer Dependencies
   - Issue: Lock file out of sync with composer.json
   - Solution: Added `composer clearcache` and updated the dependency installation process

2. Laravel Key Generation
   - Issue: Facade root not set
   - Solution: 
     - Moved Laravel initialization to direct file operations instead of artisan commands
     - Added bootstrap-app.php to fix the facade root issue at application startup
     - Created facade-fix.php for direct façade root initialization
     - Implemented alternative entry points (alt-index.php) for better diagnostics
     - Added diagnose-facade.php for comprehensive diagnosis and fixes

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

10. Bootstrap Script Creation Error
    - Issue: Complex echo command in Dockerfile causing build failures
    - Solution: Created a separate bootstrap.sh file and used COPY in the Dockerfile instead

11. MySQL Hostname Resolution Issue
    - Issue: Internal hostname mysql.railway.internal not resolving properly
    - Solution: Added support for external hostname/port from MYSQL_PUBLIC_URL environment variable 
    - Added auto-detection and failover between internal and external connection methods

12. Railway MySQL Connection Issues
    - Issue: mysql.railway.internal hostname not resolving in Railway environment
    - Solutions:
      - Ensure MySQL plugin is properly linked to your app service
      - Use the external hostname and port from MYSQL_PUBLIC_URL environment variable
      - The updated bootstrap.sh now automatically tries both internal and external connections
      - Visit /public/db-connect-fix.php to automatically fix connection issues

13. Database Initialization Issues
    - Issue: Empty database after deployment
    - Solution: Use the provided scripts in order: run-migrations.php, repair-database.php, create-admin.php, fix-permissions.php
    - If scripts fail with permission errors, run `chmod 755 /var/www/html/public/*.php` in the Railway shell

14. PHP Version Compatibility
    - Issue: Syntax errors due to newer PHP features used in scripts
    - Solution: Added backward compatible `db-fixed.php` file that works on older PHP versions
    - Replaced nullish coalescing operator (??) with traditional isset() checks
    - Modified heredoc syntax for PHP 5.x compatibility
    - Used array() syntax instead of [] shorthand for older PHP compatibility
    - Created simplified bootstrapping procedure with older version support

15. File Permission Issues in Restricted Environments
    - Issue: Unable to write configuration files in some hosting environments
    - Solution: Created memory-only versions of key configuration scripts
    - Added `memory-only-fix.php` that configures database without writing files
    - Created `run-migrations-memory.php` that runs all setup steps without file access
    - Implemented Excel dependency stub generation to avoid package installation errors
    - Used $_ENV, putenv() and $GLOBALS for in-memory configuration instead of file writes
    - If you encounter permission issues:
      1. Visit `/public/memory-only-fix.php` to verify database connection
      2. Visit `/public/run-migrations-memory.php` to run migrations in memory
      3. If Excel dependency is causing issues, the memory script includes automatic stub generation

16. Artisan Command Failures
    - Issue: All artisan commands fail with error code 255 during migration
    - Solution: Created `direct-migration.php` that bypasses Laravel completely
    - This script creates essential database tables using direct SQL statements
    - Sets up a minimal admin user and basic system configuration
    - Uses PDO for direct database access without Laravel dependencies
    - Requires the memory-only-fix.php to establish database connection
    - Resolves common issues with required fields by providing default values
    - Implements proper error handling with detailed SQL error reporting
    - Automatically detects and uses the correct application URL
    - If you encounter artisan command failures:
      1. Visit `/public/direct-migration.php` to set up the database directly
      2. After setup completes, continue with standard admin user creation
      3. Change the default admin password immediately after login

17. Database Field Constraint Issues
    - Issue: User creation fails with errors like "Field 'ext' doesn't have a default value"
    - Solution: Updated schema definitions in direct-migration.php to include:
      - Default values for all required fields
      - Modified field definitions for better compatibility
      - Enhanced error reporting to show specific constraint violations
    - Fields like ext, country_code, and phone_number now have appropriate defaults
    - Improved error handling shows the exact SQL state and error codes
    - If user creation fails when using direct-migration.php, check the error message for:
      - Missing required fields
      - Constraint violations
      - Data type mismatches
      
18. Laravel Facade Root Error
    - Issue: "A facade root has not been set" error when accessing the application
    - Solutions:
      - Visit `/public/diagnose-facade.php` to run diagnostics and apply fixes
      - Use `/public/fix-bootstrap.php` to patch index.php and fix permissions
      - Ensure bootstrap-app.php is included at the beginning of index.php
      - Try accessing the application through `/public/alt-index.php`
      - Check that storage and bootstrap/cache directories have proper permissions
      - Clear Laravel configuration cache if needed

19. Missing or Incomplete Vendor Files
    - Issue: Laravel classes not found, "Class not found" errors after deployment
    - Solutions:
      - Visit `diagnose-facade.php` which will automatically detect the issue
      - Use `install-dependencies.php` to reinstall all dependencies
      - For command line access: `composer install --no-dev --optimize-autoloader`
      - Check for the `needs_composer_install` flag in public directory, which indicates bootstrap installation failure
      - If dependency installation fails repeatedly, check for memory limits or disk space issues

20. URL Redirect Issues
    - Issue: Application redirects to port 8080 or localhost
    - Solution:
      - Integrated URL fix directly into bootstrap.sh process
      - Detects and applies correct URL during container startup
      - Runs with root privileges to avoid permission issues
      - Updates database, config files, and environment variables
      - Provides fallback PHP script (url-redirect-fix.php) for manual fixes

### Memory-Only Fix Solution
The project includes a special set of memory-only tools to handle deployment environments with restricted file permissions:

### memory-only-fix.php
A zero-footprint database configuration helper that:
- Works entirely in memory without writing any files to disk
- Avoids permission errors common in restricted deployment environments
- Tests multiple connection methods (Environment Variables, Railway Variables, DATABASE_URL, Railway Internal, .env File)
- Sets database configuration directly in memory using $_ENV, putenv() and $GLOBALS
- Provides detailed diagnostic information through a web interface
- Returns connection results for use in other scripts
- Shows database tables when connection is successful

### run-migrations-memory.php
A memory-only version of the migration script that:
- Uses the memory-only configuration helper to avoid file permission issues
- Creates a stub for Excel dependency to fix missing package errors
- Runs database commands with increased memory limits
- Handles migration failures gracefully with detailed error reporting
- Verifies database tables after migration

These tools work together to ensure reliable database setup even in environments with limited PHP versions or constrained resources.

## Future Improvements
1. Automated dependency updates
2. Enhanced error handling
3. Improved build process
4. Better documentation
5. Address class ambiguity warnings through proper namespace management
6. Create a dedicated Railway configuration section in the bootstrap script
7. Implement proper Laravel Mix asset compilation
8. Add more comprehensive diagnostic tools for other common issues
9. Create additional memory-only versions of other maintenance tools (repair-database, create-admin, fix-permissions)
10. Add support for more database connection methods in memory-only tools
11. Implement better error reporting in facade diagnostic scripts
12. Streamline the bootstrapping process to eliminate redundancy
13. Improve the user interface of diagnostic tools for better usability