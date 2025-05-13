#!/bin/bash
set -e

echo "Running Composer..."
composer clearcache
composer install --no-scripts --no-autoloader || true
composer dump-autoload --optimize --no-scripts || true

echo "Creating necessary directories..."
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public

echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Setting up Laravel..."
# Make sure the .env file exists and has a key
if [ ! -f /var/www/html/.env ]; then
  echo "Creating .env file..."
  cp /var/www/html/.env.example /var/www/html/.env || true
fi

# Create health check file for Railway
echo "<?php echo \"OK\"; ?>" > /var/www/html/public/health.php

# Create db test file
echo "<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h1>Database Connection Test</h1>';

// File System Checks
echo '<h2>File System Checks</h2>';
\$envPath = '/var/www/html/.env';
echo '<p>.env path: ' . \$envPath . '</p>';
echo '<p>.env exists: ' . (file_exists(\$envPath) ? 'Yes' : 'No') . '</p>';

if (file_exists(\$envPath)) {
  echo '<p>.env file permissions: ' . substr(sprintf('%o', fileperms(\$envPath)), -4) . '</p>';
  echo '<p>.env file readable: ' . (is_readable(\$envPath) ? 'Yes' : 'No') . '</p>';
  echo '<p>.env file owner: ' . posix_getpwuid(fileowner(\$envPath))['name'] . '</p>';
  echo '<p>.env file size: ' . filesize(\$envPath) . ' bytes</p>';
  
  // Show first few lines of .env
  echo '<p>First 10 lines of .env file:</p>';
  \$lines = file(\$envPath, FILE_IGNORE_NEW_LINES);
  for (\$i = 0; \$i < min(10, count(\$lines)); \$i++) {
    if (strpos(\$lines[\$i], 'PASSWORD') !== false) {
      echo preg_replace('/PASSWORD=.*/', 'PASSWORD=[hidden]', \$lines[\$i]) . '<br>';
    } else {
      echo \$lines[\$i] . '<br>';
    }
  }
  
  // Parse .env file for DB settings
  echo '<h2>Environment Variables from .env</h2>';
  \$connection = \$host = \$port = \$database = \$username = '';
  foreach (\$lines as \$line) {
    if (strpos(\$line, 'DB_CONNECTION=') === 0) \$connection = substr(\$line, strlen('DB_CONNECTION='));
    if (strpos(\$line, 'DB_HOST=') === 0) \$host = substr(\$line, strlen('DB_HOST='));
    if (strpos(\$line, 'DB_PORT=') === 0) \$port = substr(\$line, strlen('DB_PORT='));
    if (strpos(\$line, 'DB_DATABASE=') === 0) \$database = substr(\$line, strlen('DB_DATABASE='));
    if (strpos(\$line, 'DB_USERNAME=') === 0) \$username = substr(\$line, strlen('DB_USERNAME='));
  }
  
  echo '<p>Connection: ' . \$connection . '</p>';
  echo '<p>Host: ' . \$host . '</p>';
  echo '<p>Port: ' . \$port . '</p>';
  echo '<p>Database: ' . \$database . '</p>';
  echo '<p>Username: ' . \$username . '</p>';
}

// Railway Environment Variables
echo '<h2>Railway Environment Variables</h2>';
echo '<p>RAILWAY_ENVIRONMENT: ' . getenv('RAILWAY_ENVIRONMENT') . '</p>';
echo '<p>MYSQLHOST: ' . getenv('MYSQLHOST') . '</p>';
echo '<p>MYSQLPORT: ' . getenv('MYSQLPORT') . '</p>';
echo '<p>MYSQLDATABASE: ' . getenv('MYSQLDATABASE') . '</p>';
echo '<p>MYSQLUSER: ' . getenv('MYSQLUSER') . '</p>';
echo '<p>MYSQLPASSWORD: ' . (getenv('MYSQLPASSWORD') ? 'Set (hidden)' : 'Not set') . '</p>';

// Connection Test
echo '<h2>Connection Test</h2>';
echo '<p>Using direct environment variables instead of .env</p>';

\$host = getenv('MYSQLHOST');
\$port = getenv('MYSQLPORT') ?: '3306';
\$database = getenv('MYSQLDATABASE');
\$username = getenv('MYSQLUSER');
\$password = getenv('MYSQLPASSWORD');

// Connection string
\$dsn = \"mysql:host={\$host};port={\$port};dbname={\$database}\";
echo \"<p>Attempting to connect to: {\$dsn}</p>\";

try {
  \$pdo = new PDO(\$dsn, \$username, \$password);
  \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo '<p style=\"color:green\">✓ Connection successful!</p>';
  
  // Show some database information
  \$stmt = \$pdo->query('SELECT VERSION()');
  \$version = \$stmt->fetchColumn();
  echo '<p>MySQL Version: ' . \$version . '</p>';
  
  \$stmt = \$pdo->query('SHOW TABLES');
  \$tables = \$stmt->fetchAll(PDO::FETCH_COLUMN);
  echo '<p>Tables found: ' . count(\$tables) . '</p>';
  if (count(\$tables) > 0) {
    echo '<ul>';
    foreach (\$tables as \$table) {
      echo '<li>' . \$table . '</li>';
    }
    echo '</ul>';
  }
} catch (PDOException \$e) {
  echo '<p style=\"color:red\">✗ Connection failed:</p>';
  echo '<p>' . \$e->getMessage() . '</p>';
  
  // Hostname resolution test
  echo '<h3>Hostname Resolution Test</h3>';
  echo '<p>Resolving ' . \$host . ': ';
  \$ip = gethostbyname(\$host);
  if (\$ip != \$host) {
    echo 'Success (' . \$ip . ')</p>';
  } else {
    echo 'Failed (could not resolve)</p>';
  }
  
  // Check DNS records
  echo '<p>DNS Records for ' . \$host . ':</p>';
  echo '<pre>';
  print_r(dns_get_record(\$host));
  echo '</pre>';
  
  // Try alternative hostnames
  echo '<h3>Trying alternative connection methods:</h3>';
  
  // Direct using Railway variables
  try {
    \$directDsn = \"mysql:host={\$host};port={\$port};dbname={\$database}\";
    echo \"<p>Trying direct Railway variables connection:</p>\";
    echo \"<p>DSN: {\$directDsn}</p>\";
    \$directPdo = new PDO(\$directDsn, \$username, \$password);
    \$directPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo '<p style=\"color:green\">SUCCESS!</p>';
  } catch (PDOException \$e) {
    echo '<p style=\"color:red\">FAILED: ' . \$e->getMessage() . '</p>';
  }
  
  // Try common hostnames in Railway
  echo '<h3>Trying common MySQL hostnames in Railway:</h3>';
  \$hostnames = ['mysql', 'db', 'database', 'mysql-service', 'mysql.internal', 'localhost', '127.0.0.1'];
  
  foreach (\$hostnames as \$testHost) {
    try {
      \$testDsn = \"mysql:host={\$testHost};port={\$port};dbname={\$database}\";
      echo \"<p>Testing host '{\$testHost}': {\$testDsn}</p>\";
      \$testPdo = new PDO(\$testDsn, \$username, \$password);
      \$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      echo '<p style=\"color:green\">SUCCESS for ' . \$testHost . '!</p>';
    } catch (PDOException \$e) {
      echo '<p style=\"color:red\">FAILED for ' . \$testHost . ': ' . \$e->getMessage() . '</p>';
    }
  }
}

// MySQL Service Status Check
echo '<h2>MySQL Service Status Check</h2>';
if (getenv('RAILWAY_ENVIRONMENT')) {
  echo '<p>This is a Railway deployment, so we can\'t directly check service status from PHP.</p>';
  echo '<p>Please check the Railway dashboard for the MySQL service status.</p>';
} else {
  // Local environment
  echo '<p>This is a local development environment.</p>';
  echo '<p>MySQL service status:</p>';
  echo '<pre>';
  system('service mysql status 2>&1');
  echo '</pre>';
}
?>" > /var/www/html/public/db-test.php

# Handle Railway environment
if [ -n "$RAILWAY_ENVIRONMENT" ]; then
  echo "Running in Railway environment..."
  
  # Debug MySQL environment variables
  echo "MySQL Environment Variables:"
  echo "MYSQLHOST: ${MYSQLHOST:-not set}"
  echo "MYSQLPORT: ${MYSQLPORT:-not set}"
  echo "MYSQLDATABASE: ${MYSQLDATABASE:-not set}"
  echo "MYSQLUSER: ${MYSQLUSER:-not set}"
  echo "MYSQLPASSWORD: ${MYSQLPASSWORD:+is set}"
  
  # Check if we have a MySQL public URL available
  MYSQL_PUBLIC_URL=${MYSQL_PUBLIC_URL:-}
  if [ -n "$MYSQL_PUBLIC_URL" ]; then
    echo "Using MySQL Public URL: (masked for security)"
    
    # Extract host and port from the public URL
    # Format: mysql://user:pass@hostname:port/database
    MYSQL_PUBLIC_HOST=$(echo "$MYSQL_PUBLIC_URL" | sed -n 's/.*@\([^:]*\).*/\1/p')
    MYSQL_PUBLIC_PORT=$(echo "$MYSQL_PUBLIC_URL" | sed -n 's/.*:\([0-9]*\)\/.*/\1/p')
    
    echo "Extracted public host: $MYSQL_PUBLIC_HOST"
    echo "Extracted public port: $MYSQL_PUBLIC_PORT"
  else
    echo "No MySQL Public URL found, using internal Railway hostname"
    MYSQL_PUBLIC_HOST=""
    MYSQL_PUBLIC_PORT=""
  fi
  
  # Set Apache ServerName to suppress the warning
  echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
  a2enconf servername
  
  # Create a small program to test multiple database connection methods
  cat > /tmp/db-connect-test.php << 'EOF'
<?php
// Attempt to connect to the database using multiple methods
$result = array(
  'success' => false,
  'host' => '',
  'port' => '',
  'message' => ''
);

// Get connection details
$internal_host = getenv('MYSQLHOST');
$internal_port = getenv('MYSQLPORT');
$public_url = getenv('MYSQL_PUBLIC_URL');
$public_host = '';
$public_port = '';
$database = getenv('MYSQLDATABASE');
$username = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');

// Parse public URL if available
if ($public_url) {
  $parsed = parse_url($public_url);
  if ($parsed) {
    $public_host = $parsed['host'] ?? '';
    $public_port = $parsed['port'] ?? '';
  }
}

// Test public connection first
if ($public_host && $public_port) {
  try {
    $dsn = "mysql:host={$public_host};port={$public_port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $result['success'] = true;
    $result['host'] = $public_host;
    $result['port'] = $public_port;
    $result['message'] = "Connected using public URL";
    echo json_encode($result);
    exit;
  } catch (PDOException $e) {
    // Continue to next method if this fails
  }
}

// Test internal connection if public failed
if ($internal_host && $internal_port) {
  try {
    $dsn = "mysql:host={$internal_host};port={$internal_port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $result['success'] = true;
    $result['host'] = $internal_host;
    $result['port'] = $internal_port;
    $result['message'] = "Connected using internal hostname";
    echo json_encode($result);
    exit;
  } catch (PDOException $e) {
    // Continue to next method if this fails
  }
}

// Try hardcoded external hostname
$external_host = 'yamabiko.proxy.rlwy.net';
$external_port = '52501';
try {
  $dsn = "mysql:host={$external_host};port={$external_port};dbname={$database}";
  $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $result['success'] = true;
  $result['host'] = $external_host;
  $result['port'] = $external_port;
  $result['message'] = "Connected using hardcoded external hostname";
  echo json_encode($result);
  exit;
} catch (PDOException $e) {
  // If all methods fail, return error
  $result['message'] = "All connection methods failed";
  echo json_encode($result);
  exit;
}
EOF

  # Run the test script to find the best connection method
  echo "Testing database connection methods..."
  CONNECTION_TEST=$(php /tmp/db-connect-test.php)
  echo "Connection test result: $CONNECTION_TEST"
  
  # Parse the JSON result
  CONNECTION_SUCCESS=$(echo "$CONNECTION_TEST" | grep -o '"success":true' || echo "")
  DB_HOST=$(echo "$CONNECTION_TEST" | sed -n 's/.*"host":"\([^"]*\)".*/\1/p')
  DB_PORT=$(echo "$CONNECTION_TEST" | sed -n 's/.*"port":"\([^"]*\)".*/\1/p')
  
  if [ -n "$CONNECTION_SUCCESS" ] && [ -n "$DB_HOST" ]; then
    echo "Successfully connected to database at $DB_HOST:$DB_PORT"
    
    # Use the successful connection details for .env file
    cat > /var/www/html/.env << EOF
APP_NAME=Faveo
APP_ENV=production
APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew=
APP_DEBUG=true
APP_URL=${APP_URL:-http://localhost}

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=${MYSQLDATABASE:-railway}
DB_USERNAME=${MYSQLUSER:-root}
DB_PASSWORD=${MYSQLPASSWORD}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

FCM_SERVER_KEY=
FCM_SENDER_ID=
EOF

    # Create direct database bootstrap file with working connection
    cat > /var/www/html/public/db_bootstrap.php << EOF
<?php
// Direct database configuration for Laravel on Railway

// Force database configuration through Laravel Config system
\$_ENV['DB_CONNECTION'] = 'mysql';
\$_ENV['DB_HOST'] = '$DB_HOST';
\$_ENV['DB_PORT'] = '$DB_PORT';
\$_ENV['DB_DATABASE'] = '${MYSQLDATABASE:-railway}';
\$_ENV['DB_USERNAME'] = '${MYSQLUSER:-root}';
\$_ENV['DB_PASSWORD'] = '${MYSQLPASSWORD}';

// Also set them in environment to be doubly sure
putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=$DB_HOST');
putenv('DB_PORT=$DB_PORT');
putenv('DB_DATABASE=${MYSQLDATABASE:-railway}');
putenv('DB_USERNAME=${MYSQLUSER:-root}');
putenv('DB_PASSWORD=${MYSQLPASSWORD}');
EOF

  else
    echo "All database connection methods failed. Using default values and hoping for the best."
    
    # Try both the internal and external hostnames in the .env file
    cat > /var/www/html/.env << EOF
APP_NAME=Faveo
APP_ENV=production
APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew=
APP_DEBUG=true
APP_URL=${APP_URL:-http://localhost}

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=yamabiko.proxy.rlwy.net
DB_PORT=52501
DB_DATABASE=${MYSQLDATABASE:-railway}
DB_USERNAME=${MYSQLUSER:-root}
DB_PASSWORD=${MYSQLPASSWORD}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

FCM_SERVER_KEY=
FCM_SENDER_ID=
EOF

    # Create a failover bootstrap file trying both connection methods
    cat > /var/www/html/public/db_bootstrap.php << EOF
<?php
// Railway database connection with failover between internal and external hostnames

// Define connection details
\$internal_host = 'mysql.railway.internal';
\$internal_port = '3306';
\$external_host = 'yamabiko.proxy.rlwy.net';
\$external_port = '52501';
\$database = '${MYSQLDATABASE:-railway}';
\$username = '${MYSQLUSER:-root}';
\$password = '${MYSQLPASSWORD}';

// First try the external connection
\$connected = false;
try {
    \$dsn = "mysql:host={\$external_host};port={\$external_port};dbname={\$database}";
    \$pdo = new PDO(\$dsn, \$username, \$password, array(PDO::ATTR_TIMEOUT => 2));
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$connected = true;
    
    // Set environment variables to external host
    \$_ENV['DB_CONNECTION'] = 'mysql';
    \$_ENV['DB_HOST'] = \$external_host;
    \$_ENV['DB_PORT'] = \$external_port;
    \$_ENV['DB_DATABASE'] = \$database;
    \$_ENV['DB_USERNAME'] = \$username;
    \$_ENV['DB_PASSWORD'] = \$password;
    
    putenv('DB_CONNECTION=mysql');
    putenv('DB_HOST=' . \$external_host);
    putenv('DB_PORT=' . \$external_port);
    putenv('DB_DATABASE=' . \$database);
    putenv('DB_USERNAME=' . \$username);
    putenv('DB_PASSWORD=' . \$password);
} catch (PDOException \$e) {
    // External connection failed, try internal
    try {
        \$dsn = "mysql:host={\$internal_host};port={\$internal_port};dbname={\$database}";
        \$pdo = new PDO(\$dsn, \$username, \$password, array(PDO::ATTR_TIMEOUT => 2));
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$connected = true;
        
        // Set environment variables to internal host
        \$_ENV['DB_CONNECTION'] = 'mysql';
        \$_ENV['DB_HOST'] = \$internal_host;
        \$_ENV['DB_PORT'] = \$internal_port;
        \$_ENV['DB_DATABASE'] = \$database;
        \$_ENV['DB_USERNAME'] = \$username;
        \$_ENV['DB_PASSWORD'] = \$password;
        
        putenv('DB_CONNECTION=mysql');
        putenv('DB_HOST=' . \$internal_host);
        putenv('DB_PORT=' . \$internal_port);
        putenv('DB_DATABASE=' . \$database);
        putenv('DB_USERNAME=' . \$username);
        putenv('DB_PASSWORD=' . \$password);
    } catch (PDOException \$e) {
        // Both connections failed
    }
}

// Configure global database settings for Laravel
\$GLOBALS['db_config_override'] = [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => '',
            'host' => \$_ENV['DB_HOST'],
            'port' => \$_ENV['DB_PORT'],
            'database' => \$database,
            'username' => \$username,
            'password' => \$password,
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => '',
            ]) : [],
        ],
    ],
];
EOF
  fi

  # Modify the index.php file to include our bootstrap
  if [ -f "/var/www/html/public/index.php" ]; then
    # Make a backup of the original file
    if [ ! -f "/var/www/html/public/index.php.bak" ]; then
      cp /var/www/html/public/index.php /var/www/html/public/index.php.bak
    fi
    
    # Add our bootstrap include at the top of index.php
    if ! grep -q "db_bootstrap.php" /var/www/html/public/index.php; then
      sed -i '1s/^/<?php require_once __DIR__ . "\/db_bootstrap.php"; ?>\n/' /var/www/html/public/index.php
    fi
  fi
  
  # Use PORT from Railway if available
  if [ -n "$PORT" ]; then
    echo "Setting up Apache for port $PORT..."
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf || true
    sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf || true
  fi
else
  # Local development environment settings
  cat > /var/www/html/.env << EOF
APP_NAME=Faveo
APP_ENV=local
APP_KEY=base64:KLt6cSOazff/QVuWn4VNoNyTiJ0W0+HrY3f9rtAJKew=
APP_DEBUG=true
APP_URL=http://localhost:8080

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=faveo
DB_USERNAME=faveo
DB_PASSWORD=faveo_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

FCM_SERVER_KEY=
FCM_SENDER_ID=
EOF
fi

echo "Starting Apache..."
apache2-foreground 