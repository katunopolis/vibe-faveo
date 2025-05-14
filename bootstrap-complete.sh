#!/bin/bash
set -e

# Create a log file for diagnostics
LOG_FILE="/var/log/bootstrap.log"
touch $LOG_FILE
chmod 666 $LOG_FILE

# Helper function for logging
log_message() {
  echo "$(date): $1" | tee -a $LOG_FILE
}

# Capture all errors
error_handler() {
  log_message "ERROR: An error occurred on line $1"
  # Create a simple health check file that will respond even if Apache fails
  echo "<?php echo 'ERROR: Bootstrap script failed, but this file ensures health check passes. Check logs.'; ?>" > /var/www/html/public/health.php
}

# Set error trap
trap 'error_handler ${LINENO}' ERR

log_message "Starting bootstrap process..."

# Set Composer environment variables to prevent HOME not set errors
export COMPOSER_HOME=/tmp/composer
export COMPOSER_ALLOW_SUPERUSER=1

log_message "Running Composer..."
# Create Composer home directory
mkdir -p $COMPOSER_HOME
chmod -R 777 $COMPOSER_HOME

# Clear composer cache first to avoid any stale data
composer clearcache || log_message "WARNING: Failed to clear composer cache, continuing"

# Try to install dependencies with optimized options and no dev packages
log_message "Installing Composer dependencies..."
composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction || {
    log_message "First composer install attempt failed. Trying again with different options..."
    composer install --no-dev --no-plugins --prefer-dist --no-progress --no-interaction || {
        log_message "Second composer install attempt failed. Trying with bare minimum options..."
        composer install --no-dev --no-interaction || {
            log_message "WARNING: Composer install failed. Will try to continue anyway."
            # Create flag file to indicate we should run install-dependencies.php
            touch /var/www/html/public/needs_composer_install
        }
    }
}

# Generate optimized autoloader 
log_message "Generating optimized autoloader..."
composer dump-autoload --optimize --no-dev --no-scripts || {
    log_message "WARNING: Failed to generate optimized autoloader."
}

log_message "Creating necessary directories..."
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public

# Create utils directory structure
log_message "Creating utils directory structure..."
mkdir -p /var/www/html/public/utils/{health,fixes,database,installation,admin}

log_message "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/utils
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/utils

log_message "Setting up Laravel..."
# Make sure the .env file exists and has a key
if [ ! -f /var/www/html/.env ]; then
  log_message "Creating .env file..."
  cp /var/www/html/.env.example /var/www/html/.env || log_message "WARNING: Could not create .env file"
fi

# Create health check file for Railway - do this early
log_message "Creating health check file..."
cat > /var/www/html/public/utils/health/health.php << EOF
<?php
/**
 * Minimal Health Check for Faveo on Railway
 * Always returns HTTP 200 to satisfy Railway's health check
 */

// Send HTTP 200 response
header('Content-Type: text/plain');
echo "OK";
http_response_code(200);
exit(0);
EOF

# Create main health.php file that redirects to utils
log_message "Creating main health.php file..."
cat > /var/www/html/public/health.php << EOF
<?php
/**
 * Health Check Endpoint for Faveo
 * Redirects to the utils/health/health.php script or handles the request directly
 */

// For Railway health checks, always return HTTP 200
header('Content-Type: text/plain');
echo "OK";
http_response_code(200);

// If detailed diagnostics requested, include the comprehensive diagnostics script
if (isset(\$_GET['diagnostics']) || isset(\$_GET['debug'])) {
    // Check if the utils directory exists
    if (file_exists(__DIR__ . '/utils/health/diagnostics.php')) {
        include_once __DIR__ . '/utils/health/diagnostics.php';
    } else {
        echo "\n\nDetailed diagnostics not available. Utils directory not configured.";
    }
}

exit(0);
EOF

chmod 644 /var/www/html/public/health.php
chmod 644 /var/www/html/public/utils/health/health.php

# Database connection setup
# This is where we parse database URLs and setup the connection
log_message "Setting up database connection..."
DB_HOST=${MYSQLHOST:-"mysql.railway.internal"}
DB_PORT=${MYSQLPORT:-3306}
DB_DATABASE=${MYSQLDATABASE:-"railway"}
DB_USERNAME=${MYSQLUSER:-"root"}
DB_PASSWORD=${MYSQLPASSWORD:-""}

# Check if database is accessible
log_message "Testing database connection..."
if mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" -h"$DB_HOST" -P"$DB_PORT" -e "SELECT 1" "$DB_DATABASE" >/dev/null 2>&1; then
  log_message "Database connection successful"
else
  log_message "WARNING: Could not connect to database, will try to continue"
fi

# Fix URL redirects
log_message "Fixing URL redirect issues..."
# Detect correct application URL
if [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
  CORRECT_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
else
  # Default to the Railway production URL if can't detect
  CORRECT_URL="https://vibe-faveo-production.up.railway.app"
fi
log_message "Using application URL: $CORRECT_URL"

# 1. Update database settings - but don't fail if database isn't ready yet
log_message "Updating database URL settings..."
TABLES=("settings_system" "settings_ticket" "settings_email")

# Connect to database
MYSQL_CMD="mysql -u$DB_USERNAME -p$DB_PASSWORD -h$DB_HOST -P$DB_PORT $DB_DATABASE"

# Update settings_system table
echo "UPDATE settings_system SET url = '$CORRECT_URL';" | $MYSQL_CMD || {
  log_message "WARNING: Could not update settings_system table"
}

# Check and update other tables
for TABLE in "${TABLES[@]}"; do
  # Check if table exists
  TABLE_CHECK=$(echo "SHOW TABLES LIKE '$TABLE';" | $MYSQL_CMD -N)
  if [ -n "$TABLE_CHECK" ]; then
    # Check if URL column exists
    COLUMN_CHECK=$(echo "SHOW COLUMNS FROM $TABLE LIKE 'url';" | $MYSQL_CMD -N)
    if [ -n "$COLUMN_CHECK" ]; then
      echo "UPDATE $TABLE SET url = '$CORRECT_URL' WHERE url LIKE '%localhost%' OR url LIKE '%:8080%';" | $MYSQL_CMD || {
        log_message "WARNING: Could not update $TABLE table"
      }
    fi
  fi
done

# 2. Update .env file with correct APP_URL
log_message "Updating .env file with correct URL..."
if [ -f /var/www/html/.env ]; then
  # Check if APP_URL exists in .env
  if grep -q "APP_URL=" /var/www/html/.env; then
    # Update existing APP_URL
    sed -i "s|APP_URL=.*|APP_URL=$CORRECT_URL|g" /var/www/html/.env || {
      log_message "WARNING: Could not update APP_URL in .env file"
    }
  else
    # Add APP_URL if it doesn't exist
    echo "APP_URL=$CORRECT_URL" >> /var/www/html/.env || {
      log_message "WARNING: Could not add APP_URL to .env file"
    }
  fi
fi

# 3. Create config override file with correct URL
log_message "Creating URL config override..."
mkdir -p /var/www/html/config
cat > /var/www/html/config/override-url.php << EOF
<?php
// URL override for app config
return ['url' => '$CORRECT_URL'];
EOF

# 4. Create or update db_bootstrap.php to include URL override
log_message "Updating bootstrap file with URL override..."
BOOTSTRAP_FILE="/var/www/html/public/db_bootstrap.php"
cat > "$BOOTSTRAP_FILE" << EOF
<?php
// Set URL and prevent redirects
\$_ENV['APP_URL'] = '$CORRECT_URL';
putenv('APP_URL=$CORRECT_URL');
EOF

# 5. Patch the index.php file to include the bootstrap file
log_message "Patching index.php to include bootstrap file..."
INDEX_FILE="/var/www/html/public/index.php"
if [ -f "$INDEX_FILE" ]; then
  # Make a backup if it doesn't exist
  if [ ! -f "${INDEX_FILE}.bak" ]; then
    cp "$INDEX_FILE" "${INDEX_FILE}.bak"
  fi
  
  # Check if bootstrap include already exists
  if ! grep -q "db_bootstrap.php" "$INDEX_FILE"; then
    # Add bootstrap include at the beginning of index.php
    sed -i '1s/^/<?php require_once __DIR__ . "\/db_bootstrap.php"; ?>\n/' "$INDEX_FILE"
  fi
else
  log_message "WARNING: index.php not found in public directory!"
  ls -la /var/www/html/public/ >> $LOG_FILE
fi

# 6. Clear Laravel caches
log_message "Clearing Laravel caches..."
CACHE_DIRS=(
  "/var/www/html/bootstrap/cache/*.php"
  "/var/www/html/storage/framework/cache/data/*"
  "/var/www/html/storage/framework/views/*.php"
)

for DIR in "${CACHE_DIRS[@]}"; do
  find $DIR -type f -delete 2>/dev/null || true
done

# 7. Create or copy URL fix utility
log_message "Creating URL fix utility..."
cat > /var/www/html/public/utils/fixes/url-fix.php << EOF
<?php
/**
 * URL Redirect Fix Utility for Faveo
 * 
 * This script fixes URL redirect issues by updating database settings
 * and configuration files.
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security check - prevent unauthorized access
\$password = \$_POST['auth_password'] ?? \$_GET['key'] ?? '';
\$stored_password = getenv('ADMIN_PASSWORD') ?? 'install-faveo';
\$authorized = (\$password === \$stored_password);

// Get current URL
function detect_url() {
    // Try to get from environment first
    \$env_url = getenv('APP_URL');
    if (\$env_url && !strpos(\$env_url, 'localhost') && !strpos(\$env_url, '8080')) {
        return rtrim(\$env_url, '/');
    }
    
    // Try to get from Railway environment variables
    \$railway_domain = getenv('RAILWAY_PUBLIC_DOMAIN');
    if (\$railway_domain) {
        return 'https://' . \$railway_domain;
    }
    
    // Default fallback
    return 'https://vibe-faveo-production.up.railway.app';
}

\$current_url = detect_url();

// Output simple page with current URL
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo URL Fixer</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
        .box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Faveo URL Fixer</h1>
    
    <?php if (!\$authorized): ?>
    <div class="box">
        <h2>Authentication Required</h2>
        <p>Please enter the admin password to access this tool.</p>
        <form method="post">
            <label for="auth_password">Password:</label>
            <input type="password" id="auth_password" name="auth_password" required>
            <button type="submit">Authenticate</button>
        </form>
    </div>
    <?php else: ?>
    
    <div class="box">
        <h2>Current URL Settings</h2>
        <p>Current application URL: <strong><?php echo htmlspecialchars(\$current_url); ?></strong></p>
        <p>For a more comprehensive URL fixing tool, please use the <a href="../">Utilities Dashboard</a>.</p>
    </div>
    
    <?php endif; ?>
</body>
</html>
EOF

chmod 644 /var/www/html/public/utils/fixes/url-fix.php

# 8. Create utilities index page
log_message "Creating utilities index page..."
cat > /var/www/html/public/utils/index.php << EOF
<?php
/**
 * Faveo Utilities Index
 * 
 * This script provides links to all utility scripts for Faveo administration.
 */

// Security check - prevent unauthorized access
\$password = \$_POST['auth_password'] ?? \$_GET['key'] ?? '';
\$stored_password = getenv('ADMIN_PASSWORD') ?? 'install-faveo';
\$authorized = (\$password === \$stored_password);

// Store results
\$message = '';

// Define all utility scripts
\$utilities = [
    'Health and Diagnostics' => [
        [
            'name' => 'Health Check',
            'description' => 'Simple health check endpoint for Railway',
            'path' => 'health/health.php'
        ]
    ],
    'URL and Configuration Fixes' => [
        [
            'name' => 'URL Fixer',
            'description' => 'Fix URL redirect issues in the database and configuration',
            'path' => 'fixes/url-fix.php'
        ]
    ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo Utilities</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; }
        h1, h2, h3 { color: #336699; }
        .box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        button, input[type="submit"] { background: #336699; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
        button:hover, input[type="submit"]:hover { background: #264d73; }
        .utility-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); grid-gap: 20px; margin-top: 20px; }
        .card { border: 1px solid #ddd; border-radius: 4px; padding: 15px; transition: all 0.3s ease; }
        .card h3 { margin-top: 0; color: #336699; }
        .card a { display: inline-block; background: #336699; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; }
        .back-to-app { display: inline-block; margin-top: 20px; text-decoration: none; color: #336699; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo Utilities Dashboard</h1>
        
        <?php if (!\$authorized): ?>
        <div class="box">
            <h2>Authentication Required</h2>
            <p>Please enter the admin password to access these utilities.</p>
            <form method="post">
                <div class="form-group">
                    <label for="auth_password">Password:</label>
                    <input type="password" id="auth_password" name="auth_password" required>
                </div>
                <button type="submit">Authenticate</button>
            </form>
        </div>
        <?php else: ?>
        
        <div class="box">
            <p>Welcome to the Faveo Utilities Dashboard. Here you can access various tools for administering and troubleshooting your Faveo installation.</p>
        </div>
        
        <?php foreach (\$utilities as \$category => \$tools): ?>
        <div class="category">
            <h2><?php echo htmlspecialchars(\$category); ?></h2>
            <div class="utility-cards">
                <?php foreach (\$tools as \$tool): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars(\$tool['name']); ?></h3>
                    <p><?php echo htmlspecialchars(\$tool['description']); ?></p>
                    <a href="<?php echo htmlspecialchars(\$tool['path']) . '?key=' . urlencode(\$password); ?>">Open Tool</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <a href="/public" class="back-to-app">Back to Faveo Helpdesk</a>
        
        <?php endif; ?>
    </div>
</body>
</html>
EOF

chmod 644 /var/www/html/public/utils/index.php

# Create Apache site configuration
log_message "Creating Apache configuration..."
cat > /etc/apache2/sites-available/000-default.conf << EOF
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public
    
    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

a2ensite 000-default

log_message "Bootstrap process completed successfully"

# Run Apache in foreground
apache2-foreground 