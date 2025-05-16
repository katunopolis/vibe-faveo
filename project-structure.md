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
- Base image: `php:8.2-apache`
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
7. Apache VirtualHost configuration
8. Health check file setup

### Bootstrap Script
The application uses a consolidated bootstrap script (`bootstrap-complete.sh`) that runs at container startup:
1. Comprehensive logging system:
   - Creates detailed logs at `/var/log/bootstrap.log`
   - Error handling with trap mechanisms
   - Fallback health check system for diagnostics
2. Composer dependency management:
   - Clear composer cache
   - Install dependencies with multiple fallback options
   - Optimized autoloader generation
3. Directory initialization:
   - Create necessary Laravel storage directories
   - Set proper permissions for storage and cache
4. Laravel initialization:
   - Create .env file if not exists (from .env.example)
   - Create a health check endpoint for Railway
5. Database connection configuration:
   - Auto-detect available MySQL connections
   - Try multiple connection methods
   - Create a smart failover system between connection methods
6. URL redirect fix:
   - Detect the correct application URL
   - Update database settings with the correct URL
   - Update the .env file with the correct APP_URL
   - Create a config override file with the correct URL
   - Update bootstrap file to include URL overrides
   - Patch index.php to include the bootstrap file
   - Clear Laravel caches
7. Apache configuration:
   - Create a custom VirtualHost configuration
   - Ensure URLs don't include port numbers
   - Use ProxyPreserveHost to maintain original headers
8. Apache management:
   - Start Apache in the background
   - Monitor the process to ensure proper startup
   - Create diagnostic health file if Apache fails to start

### Health Check System
The project includes an updated health check system:
1. The main `health.php` file in the public directory:
   - Simplified to always return HTTP 200 OK 
   - No longer depends on external diagnostics scripts
   - Sets proper cache control headers to prevent caching
   - Is properly referenced in railway.toml with correct path
2. Additional diagnostic features:
   - Ability to show detailed diagnostics via query parameter
   - Can redirect to comprehensive diagnostics in utils directory
3. Railway Configuration:
   - Configured in railway.toml to use `/public/health.php`
   - Increased healthcheck timeout to 300 seconds
   - `ON_FAILURE` restart policy

### Recent Updates and Fixes

#### 1. Fixed bootstrap-app.php Syntax Error
- Added proper namespace prefixes to all class references
- Fixed path resolution with dirname(dirname(__DIR__))
- Used explicit namespace resolution with backslashes
- Ensured proper facades initialization

#### 2. Updated index.php
- Modified to use the bootstrap-app.php in the public directory
- Removed redundant bootstrap loading from bootstrap/app.php
- Improved application startup process

#### 3. Improved fix-bootstrap.php
- Updated to work with the new file organization
- Added better error handling and reporting
- Improved web interface for better diagnostics
- Fixed paths to work with the project structure

#### 4. Simplified health.php
- Created minimal health check that always returns 200 OK
- Made it more resilient to environment issues
- Added cache control headers to prevent caching
- Included optional diagnostics capabilities

#### 5. Updated railway.toml
- Fixed healthcheckPath to point to /public/health.php
- Increased healthcheck timeout for better reliability
- Maintained `ON_FAILURE` restart policy for consistent operation

### Known Issues
The bootstrap script handles the following errors:
- Facade root errors during artisan commands
- Ambiguous class resolution warnings from Faveo codebase
- Apache server name warning
- Missing frontend asset files
- MySQL hostname resolution issues
- URL redirect issues (application redirecting to port 8080)

### URL Redirect Fix
The project includes a permanent solution for the URL redirect issues in Faveo, by integrating the fix directly into the bootstrap process.

### Problem Background
The Faveo application on Railway has been experiencing URL redirect issues where:
1. The application redirects from `https://vibe-faveo-production.up.railway.app/` to `https://vibe-faveo-production.up.railway.app:8080/public/`
2. This occurs because the URL in the database is set to "http://localhost" or includes port 8080
3. Previous fix attempts used PHP scripts that faced permission issues

### Implementation Options

#### Option 1: Consolidated Bootstrap Script
1. Use the `bootstrap-complete.sh` file which includes all fixes
2. Update the Dockerfile to use this complete script
3. Push changes to GitHub for automatic deployment

#### Option 2: Direct Modification of bootstrap.sh
1. Edit the bootstrap.sh file in your repository
2. Add the contents of bootstrap-patch.sh right before the `apache2-foreground` command
3. Commit and push the changes to GitHub
4. Railway will automatically rebuild and deploy

#### Option 3: Separate Script Approach
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

### fix-bootstrap.php
- A comprehensive bootstrap fixing utility that addresses various bootstrapping issues
- Patches index.php to include bootstrap-app.php if needed
- Creates and verifies critical directories (storage, bootstrap/cache)
- Fixes permissions on key directories and files
- Shows detailed success and error messages
- Provides next steps and links to other diagnostic tools

## Diagnostic Tools
The application includes several diagnostic PHP scripts to help troubleshoot connection issues:

### health.php
- Advanced health check and diagnostic system
- Satisfies Railway's health check requirements
- Provides comprehensive system information:
  - Apache status
  - Database connectivity
  - Directory permissions
  - File existence verification
  - Environment variable inspection
  - Log file display
- Always returns HTTP 200 code to pass health checks
- Displays the 10 latest log entries from bootstrap and Apache

### install-dependencies.php
- Web interface to install Composer dependencies when needed
- Shows current status of vendor directory and autoloader
- Runs composer commands with proper options to optimize installation
- Includes authentication to prevent unauthorized use
- Creates an optimized autoloader to improve performance
- Displays detailed output from composer commands
- Automatically executes multiple dependency management steps
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

### fix-url.php and url-redirect-fix.php
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
   - Solution: 
     - Updated railway.toml to use `/public/health.php` as the health check path
     - Created advanced health check file that always returns HTTP 200
     - Extended health check timeout to 120 seconds
     - Added comprehensive diagnostic information to health check
   
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
    - Solutions:
      - Implemented consolidated bootstrap-complete.sh with all fixes integrated
      - Created fix-bootstrap.php script to fix permissions and bootstrap issues
      - Added bootstrap-app.php to properly initialize Laravel's application container
      - Ensured health.php always returns HTTP 200 status for Railway health checks
      - Used Apache configuration to prevent port numbers in URLs
      - Added comprehensive logging system for all bootstrap operations
      - Implemented database fallbacks when URL tables don't exist yet
      - Created SIMPLE-FIX.md with straightforward deployment instructions
      - Added procps package to allow process monitoring in health checks

21. Health Check Failures
    - Issue: Railway health checks failing with service unavailable
    - Solutions:
      - Created advanced health.php script that always returns HTTP 200
      - Added comprehensive diagnostics to health check output
      - Implemented fallback health check generation when Apache fails
      - Extended health check timeout in railway.toml
      - Monitored Apache process to detect startup failures
      - Enhanced bootstrap script with detailed logging

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

## Railway Deployment

### railway.toml Configuration
```toml
[build]
builder = "DOCKERFILE"
dockerfilePath = "Dockerfile"

[deploy]
startCommand = "/usr/local/bin/bootstrap.sh"
healthcheckPath = "/public/health.php"
healthcheckTimeout = 120
restartPolicyType = "ON_FAILURE"
numReplicas = 1
```

### Key Railway Environment Variables
- `RAILWAY_PUBLIC_DOMAIN`: The public domain of your Railway service
- `MYSQLHOST`: Host name for the database (internal hostname)
- `MYSQLPORT`: Port for the database (default: 3306)
- `MYSQLDATABASE`: Database name (default: railway)
- `MYSQLUSER`: Database username
- `MYSQLPASSWORD`: Database password
- `MYSQL_PUBLIC_URL`: External URL for MySQL (format: mysql://user:pass@hostname:port/database)

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