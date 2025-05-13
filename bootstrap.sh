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

# Create a parser script for the DATABASE_URL
cat > /tmp/parse_db_url.php << 'EOF'
<?php
// Parse various database URL formats
$connection_info = array(
  'host' => '',
  'port' => '',
  'database' => '',
  'username' => '',
  'password' => '',
  'success' => false,
  'message' => ''
);

// Try DATABASE_URL first (Railway recommended way)
$database_url = getenv('DATABASE_URL');
if ($database_url) {
  // Check if the DATABASE_URL contains the unexpanded variable format ${{MYSQL.MYSQL_URL}}
  if (strpos($database_url, '${{') !== false && strpos($database_url, '}}') !== false) {
    $connection_info['message'] = "DATABASE_URL contains unexpanded variable reference: $database_url";
    // Skip this method as it won't work with unexpanded variables
  } else {
    try {
      $parsed = parse_url($database_url);
      if ($parsed) {
        $connection_info['host'] = $parsed['host'] ?? '';
        $connection_info['port'] = $parsed['port'] ?? '3306';
        $connection_info['database'] = ltrim($parsed['path'] ?? '', '/');
        $connection_info['username'] = $parsed['user'] ?? '';
        $connection_info['password'] = $parsed['pass'] ?? '';
        
        // Test the connection
        $dsn = "mysql:host={$connection_info['host']};port={$connection_info['port']};dbname={$connection_info['database']}";
        $conn = new PDO($dsn, $connection_info['username'], $connection_info['password'], array(PDO::ATTR_TIMEOUT => 3));
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $connection_info['success'] = true;
        $connection_info['message'] = "Connected using DATABASE_URL";
      }
    } catch (Exception $e) {
      $connection_info['message'] = "DATABASE_URL parse error: " . $e->getMessage();
    }
  }
} else {
  $connection_info['message'] = "DATABASE_URL environment variable not found, trying alternatives";
}

// If DATABASE_URL didn't work, try MYSQL_URL (Railway specific)
if (!$connection_info['success']) {
  $mysql_url = getenv('MYSQL_URL');
  if ($mysql_url) {
    // Check if the MYSQL_URL contains the unexpanded variable format
    if (strpos($mysql_url, '${{') !== false && strpos($mysql_url, '}}') !== false) {
      $connection_info['message'] .= ", MYSQL_URL contains unexpanded variable reference: $mysql_url";
      // Skip this method as it won't work with unexpanded variables
    } else {
      try {
        $parsed = parse_url($mysql_url);
        if ($parsed) {
          $connection_info['host'] = $parsed['host'] ?? '';
          $connection_info['port'] = $parsed['port'] ?? '3306';
          $connection_info['database'] = ltrim($parsed['path'] ?? '', '/');
          $connection_info['username'] = $parsed['user'] ?? '';
          $connection_info['password'] = $parsed['pass'] ?? '';
          
          // Test the connection
          $dsn = "mysql:host={$connection_info['host']};port={$connection_info['port']};dbname={$connection_info['database']}";
          $conn = new PDO($dsn, $connection_info['username'], $connection_info['password'], array(PDO::ATTR_TIMEOUT => 3));
          $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          
          $connection_info['success'] = true;
          $connection_info['message'] = "Connected using MYSQL_URL";
        }
      } catch (Exception $e) {
        $connection_info['message'] .= ", MYSQL_URL parse error: " . $e->getMessage();
      }
    }
  } else {
    $connection_info['message'] .= ", MYSQL_URL not found";
  }
}

// If those didn't work, try the traditional Railway variables
if (!$connection_info['success']) {
  // Try Public URL format
  $mysql_public_url = getenv('MYSQL_PUBLIC_URL');
  if ($mysql_public_url) {
    // Check if the MYSQL_PUBLIC_URL contains the unexpanded variable format
    if (strpos($mysql_public_url, '${{') !== false && strpos($mysql_public_url, '}}') !== false) {
      $connection_info['message'] .= ", MYSQL_PUBLIC_URL contains unexpanded variable reference: $mysql_public_url";
      // Skip this method as it won't work with unexpanded variables
    } else {
      try {
        $parsed = parse_url($mysql_public_url);
        if ($parsed) {
          $connection_info['host'] = $parsed['host'] ?? '';
          $connection_info['port'] = $parsed['port'] ?? '3306';
          $connection_info['database'] = ltrim($parsed['path'] ?? '', '/');
          $connection_info['username'] = $parsed['user'] ?? '';
          $connection_info['password'] = $parsed['pass'] ?? '';
          
          // Test the connection
          $dsn = "mysql:host={$connection_info['host']};port={$connection_info['port']};dbname={$connection_info['database']}";
          $conn = new PDO($dsn, $connection_info['username'], $connection_info['password'], array(PDO::ATTR_TIMEOUT => 3));
          $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          
          $connection_info['success'] = true;
          $connection_info['message'] = "Connected using MYSQL_PUBLIC_URL";
        }
      } catch (Exception $e) {
        $connection_info['message'] .= ", MYSQL_PUBLIC_URL parse error: " . $e->getMessage();
      }
    }
  } else {
    $connection_info['message'] .= ", MYSQL_PUBLIC_URL not found";
  }
}

// If all previous methods failed, try the hardcoded Railway values
if (!$connection_info['success']) {
  // Traditional separate environment variables
  $mysqlhost = getenv('MYSQLHOST');
  $mysqlport = getenv('MYSQLPORT');
  $mysqldatabase = getenv('MYSQLDATABASE');
  $mysqluser = getenv('MYSQLUSER');
  $mysqlpassword = getenv('MYSQLPASSWORD');
  
  if ($mysqlhost && $mysqluser && $mysqldatabase) {
    try {
      $connection_info['host'] = $mysqlhost;
      $connection_info['port'] = $mysqlport ?: '3306';
      $connection_info['database'] = $mysqldatabase;
      $connection_info['username'] = $mysqluser;
      $connection_info['password'] = $mysqlpassword;
      
      // Test the connection
      $dsn = "mysql:host={$connection_info['host']};port={$connection_info['port']};dbname={$connection_info['database']}";
      $conn = new PDO($dsn, $connection_info['username'], $connection_info['password'], array(PDO::ATTR_TIMEOUT => 3));
      $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      
      $connection_info['success'] = true;
      $connection_info['message'] = "Connected using separate MYSQL* variables";
    } catch (Exception $e) {
      $connection_info['message'] .= ", Separate MYSQL* variables error: " . $e->getMessage();
    }
  } else {
    $connection_info['message'] .= ", Separate MYSQL* variables not complete";
  }
}

// Try Railway's internal networking (mysql.railway.internal)
if (!$connection_info['success']) {
  // Get password and database from environment variables if available
  $password = getenv('MYSQLPASSWORD') ?: '';
  $database = getenv('MYSQLDATABASE') ?: 'railway';
  $username = getenv('MYSQLUSER') ?: 'root';
  $port = getenv('MYSQLPORT') ?: '3306';
  
  try {
    $connection_info['host'] = 'mysql.railway.internal';
    $connection_info['port'] = $port;
    $connection_info['database'] = $database;
    $connection_info['username'] = $username;
    $connection_info['password'] = $password;
    
    // Test the connection with a short timeout
    $dsn = "mysql:host={$connection_info['host']};port={$connection_info['port']};dbname={$connection_info['database']}";
    $conn = new PDO($dsn, $connection_info['username'], $connection_info['password'], array(PDO::ATTR_TIMEOUT => 2));
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $connection_info['success'] = true;
    $connection_info['message'] = "Connected using mysql.railway.internal";
  } catch (Exception $e) {
    $connection_info['message'] .= ", mysql.railway.internal error: " . $e->getMessage();
  }
}

// If all previous methods failed, try the hardcoded external hosts
if (!$connection_info['success']) {
  // Last resort - try hardcoded values from previous findings
  try {
    $connection_info['host'] = 'yamabiko.proxy.rlwy.net';
    $connection_info['port'] = '52501';
    $connection_info['database'] = 'railway';
    $connection_info['username'] = 'root';
    // Get password from env or use an empty string
    $password = getenv('MYSQLPASSWORD') ?: '';
    $connection_info['password'] = $password;
    
    // Test the connection
    $dsn = "mysql:host={$connection_info['host']};port={$connection_info['port']};dbname={$connection_info['database']}";
    $conn = new PDO($dsn, $connection_info['username'], $connection_info['password'], array(PDO::ATTR_TIMEOUT => 3));
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $connection_info['success'] = true;
    $connection_info['message'] = "Connected using hardcoded external hostname";
  } catch (Exception $e) {
    $connection_info['message'] .= ", Hardcoded external hostname error: " . $e->getMessage();
  }
}

// Output connection info as JSON
echo json_encode($connection_info);
EOF

# Handle Railway environment
if [ -n "$RAILWAY_ENVIRONMENT" ]; then
  echo "Running in Railway environment..."
  
  # Set Apache ServerName to suppress the warning
  echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
  a2enconf servername
  
  # Check if DATABASE_URL contains unexpanded variable
  if [[ "$DATABASE_URL" == *'${{MYSQL'* ]]; then
    echo "WARNING: DATABASE_URL contains unexpanded variable format: $DATABASE_URL"
    echo "This is likely due to a Railway configuration issue. Will try alternative methods."
    # Unset DATABASE_URL so the script tries other methods
    unset DATABASE_URL
  fi
  
  # Run the parser script to get database connection details
  echo "Detecting database connection..."
  CONNECTION_INFO=$(php /tmp/parse_db_url.php)
  echo "Connection info: $CONNECTION_INFO"
  
  # Parse the JSON result
  CONNECTION_SUCCESS=$(echo "$CONNECTION_INFO" | grep -o '"success":true' || echo "")
  DB_HOST=$(echo "$CONNECTION_INFO" | sed -n 's/.*"host":"\([^"]*\)".*/\1/p')
  DB_PORT=$(echo "$CONNECTION_INFO" | sed -n 's/.*"port":"\([^"]*\)".*/\1/p')
  DB_DATABASE=$(echo "$CONNECTION_INFO" | sed -n 's/.*"database":"\([^"]*\)".*/\1/p')
  DB_USERNAME=$(echo "$CONNECTION_INFO" | sed -n 's/.*"username":"\([^"]*\)".*/\1/p')
  DB_PASSWORD=$(echo "$CONNECTION_INFO" | sed -n 's/.*"password":"\([^"]*\)".*/\1/p')
  CONNECTION_MESSAGE=$(echo "$CONNECTION_INFO" | sed -n 's/.*"message":"\([^"]*\)".*/\1/p')
  
  if [ -n "$CONNECTION_SUCCESS" ] && [ -n "$DB_HOST" ]; then
    echo "Successfully connected to database at $DB_HOST:$DB_PORT using $DB_USERNAME"
    echo "Connection method: $CONNECTION_MESSAGE"
    
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
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD

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
\$_ENV['DB_DATABASE'] = '$DB_DATABASE';
\$_ENV['DB_USERNAME'] = '$DB_USERNAME';
\$_ENV['DB_PASSWORD'] = '$DB_PASSWORD';

// Also set them in environment to be doubly sure
putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=$DB_HOST');
putenv('DB_PORT=$DB_PORT');
putenv('DB_DATABASE=$DB_DATABASE');
putenv('DB_USERNAME=$DB_USERNAME');
putenv('DB_PASSWORD=$DB_PASSWORD');

// If we have the DATABASE_URL, store it for Laravel to use directly
\$database_url = getenv('DATABASE_URL');
if (\$database_url) {
    // Skip if it contains unexpanded variables
    if (strpos(\$database_url, '\${{') === false) {
        \$_ENV['DATABASE_URL'] = \$database_url;
        putenv('DATABASE_URL=\$database_url');
    }
}
EOF

    # Create a db-test page to show current connection status
    cat > /var/www/html/public/connection-status.php << EOF
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h1>Database Connection Status</h1>';

// Get environment variables
echo '<h2>Connection Variables</h2>';
echo '<p>DATABASE_URL: ' . ((getenv('DATABASE_URL') && strpos(getenv('DATABASE_URL'), '\${{') === false) ? 'Set (hidden for security)' : (getenv('DATABASE_URL') ? 'Contains unexpanded variable: ' . htmlspecialchars(getenv('DATABASE_URL')) : 'Not set')) . '</p>';
echo '<p>MYSQL_URL: ' . ((getenv('MYSQL_URL') && strpos(getenv('MYSQL_URL'), '\${{') === false) ? 'Set (hidden for security)' : (getenv('MYSQL_URL') ? 'Contains unexpanded variable: ' . htmlspecialchars(getenv('MYSQL_URL')) : 'Not set')) . '</p>';
echo '<p>DB_HOST: ' . getenv('DB_HOST') . '</p>';
echo '<p>DB_PORT: ' . getenv('DB_PORT') . '</p>';
echo '<p>DB_DATABASE: ' . getenv('DB_DATABASE') . '</p>';
echo '<p>DB_USERNAME: ' . getenv('DB_USERNAME') . '</p>';
echo '<p>DB_PASSWORD: ' . (getenv('DB_PASSWORD') ? 'Set (hidden for security)' : 'Not set') . '</p>';

// Show Railway environment variables
echo '<h2>Railway Variables</h2>';
echo '<p>MYSQLHOST: ' . (getenv('MYSQLHOST') ?: 'Not set') . '</p>';
echo '<p>MYSQLPORT: ' . (getenv('MYSQLPORT') ?: 'Not set') . '</p>';
echo '<p>MYSQLDATABASE: ' . (getenv('MYSQLDATABASE') ?: 'Not set') . '</p>';
echo '<p>MYSQLUSER: ' . (getenv('MYSQLUSER') ?: 'Not set') . '</p>';
echo '<p>MYSQLPASSWORD: ' . (getenv('MYSQLPASSWORD') ? 'Set (hidden for security)' : 'Not set') . '</p>';

// Test the current connection
echo '<h2>Current Connection Test</h2>';
try {
    \$dsn = 'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE');
    echo '<p>DSN: ' . \$dsn . '</p>';
    
    \$conn = new PDO(\$dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<p style="color:green">✓ Connection successful!</p>';
    
    // Show tables
    \$stmt = \$conn->query('SHOW TABLES');
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
    echo '<p style="color:red">✗ Connection failed: ' . \$e->getMessage() . '</p>';
}

// Parse DATABASE_URL as a test
if (getenv('DATABASE_URL') && strpos(getenv('DATABASE_URL'), '\${{') === false) {
    echo '<h2>DATABASE_URL Parsing Test</h2>';
    try {
        \$url = parse_url(getenv('DATABASE_URL'));
        echo '<p>Host: ' . \$url['host'] . '</p>';
        echo '<p>Port: ' . \$url['port'] . '</p>';
        echo '<p>Database: ' . ltrim(\$url['path'], '/') . '</p>';
        echo '<p>Username: ' . \$url['user'] . '</p>';
        echo '<p>Password: ' . (isset(\$url['pass']) ? 'Set (hidden for security)' : 'Not set') . '</p>';
        
        // Test connection with parsed values
        \$dsn = 'mysql:host=' . \$url['host'] . ';port=' . \$url['port'] . ';dbname=' . ltrim(\$url['path'], '/');
        \$conn2 = new PDO(\$dsn, \$url['user'], \$url['pass']);
        \$conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo '<p style="color:green">✓ CONNECTION VIA DATABASE_URL SUCCESSFUL!</p>';
    } catch (Exception \$e) {
        echo '<p style="color:red">✗ DATABASE_URL parsing failed: ' . \$e->getMessage() . '</p>';
    }
} else if (getenv('DATABASE_URL')) {
    echo '<h2>DATABASE_URL Issue</h2>';
    echo '<p style="color:orange">⚠ DATABASE_URL contains unexpanded variable: ' . htmlspecialchars(getenv('DATABASE_URL')) . '</p>';
    echo '<p>This is a Railway configuration issue. Please update the variable in Railway dashboard.</p>';
}

// Test Railway internal networking connection
echo '<h2>Railway Internal Networking Test</h2>';
try {
    \$internal_host = 'mysql.railway.internal';
    \$port = getenv('MYSQLPORT') ?: '3306';
    \$database = getenv('MYSQLDATABASE') ?: 'railway';
    \$username = getenv('MYSQLUSER') ?: 'root';
    \$password = getenv('MYSQLPASSWORD') ?: '';
    
    \$dsn = "mysql:host={\$internal_host};port={\$port};dbname={\$database}";
    echo '<p>DSN: ' . \$dsn . '</p>';
    
    \$conn_internal = new PDO(\$dsn, \$username, \$password, array(PDO::ATTR_TIMEOUT => 3));
    \$conn_internal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<p style="color:green">✓ INTERNAL RAILWAY CONNECTION SUCCESSFUL!</p>';
    echo '<p>Railway internal networking is working correctly.</p>';
} catch (PDOException \$e) {
    echo '<p style="color:red">✗ Railway internal networking connection failed: ' . \$e->getMessage() . '</p>';
    echo '<p>Possible reasons for internal networking failure:</p>';
    echo '<ul>';
    echo '<li>MySQL service not properly linked to the app service</li>';
    echo '<li>MySQL service not running or not ready</li>';
    echo '<li>Network DNS issues within Railway</li>';
    echo '</ul>';
}

// Test Railway external hostname connection
echo '<h2>Railway External Hostname Test</h2>';
try {
    \$external_host = 'yamabiko.proxy.rlwy.net';
    \$external_port = '52501';
    \$database = getenv('MYSQLDATABASE') ?: 'railway';
    \$username = getenv('MYSQLUSER') ?: 'root';
    \$password = getenv('MYSQLPASSWORD') ?: '';
    
    \$dsn = "mysql:host={\$external_host};port={\$external_port};dbname={\$database}";
    echo '<p>DSN: ' . \$dsn . '</p>';
    
    \$conn_external = new PDO(\$dsn, \$username, \$password, array(PDO::ATTR_TIMEOUT => 3));
    \$conn_external->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<p style="color:green">✓ EXTERNAL HOSTNAME CONNECTION SUCCESSFUL!</p>';
} catch (PDOException \$e) {
    echo '<p style="color:red">✗ External hostname connection failed: ' . \$e->getMessage() . '</p>';
}

// Show Railway linking instructions
echo '<h2>Railway Configuration Help</h2>';
echo '<p>If you are having connection issues, ensure your MySQL service is properly linked to your app service:</p>';
echo '<ol>';
echo '<li>Go to Railway dashboard</li>';
echo '<li>Select your app service</li>';
echo '<li>Go to "Variables" tab</li>';
echo '<li>Click "Connect" or "Link Service" and select your MySQL service</li>';
echo '<li>This will add properly formatted connection variables</li>';
echo '</ol>';

echo '<h3>Connection Priority</h3>';
echo '<p>The bootstrap script tries connection methods in this order:</p>';
echo '<ol>';
echo '<li>DATABASE_URL (if set and properly formatted)</li>';
echo '<li>MYSQL_URL (if set and properly formatted)</li>';
echo '<li>MYSQL_PUBLIC_URL (if set and properly formatted)</li>';
echo '<li>Individual MYSQL* environment variables</li>';
echo '<li>mysql.railway.internal (internal networking)</li>';
echo '<li>yamabiko.proxy.rlwy.net (or your external hostname)</li>';
echo '</ol>';
EOF

  else
    echo "All database connection methods failed. Using default values and hoping for the best."
    echo "Connection error: $CONNECTION_MESSAGE"
    
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
DB_DATABASE=railway
DB_USERNAME=root
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
// Railway database connection with failover between connection methods

// Try the DATABASE_URL environment variable first (new Railway recommendation)
\$database_url = getenv('DATABASE_URL');
if (\$database_url && strpos(\$database_url, '\${{') === false) {
    try {
        \$url = parse_url(\$database_url);
        \$host = \$url['host'] ?? 'yamabiko.proxy.rlwy.net';
        \$port = \$url['port'] ?? '52501';
        \$database = ltrim(\$url['path'] ?? '/railway', '/');
        \$username = \$url['user'] ?? 'root';
        \$password = \$url['pass'] ?? '';
        
        // Test the connection
        \$dsn = "mysql:host={\$host};port={\$port};dbname={\$database}";
        \$pdo = new PDO(\$dsn, \$username, \$password, array(PDO::ATTR_TIMEOUT => 2));
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set environment variables
        \$_ENV['DB_CONNECTION'] = 'mysql';
        \$_ENV['DB_HOST'] = \$host;
        \$_ENV['DB_PORT'] = \$port;
        \$_ENV['DB_DATABASE'] = \$database;
        \$_ENV['DB_USERNAME'] = \$username;
        \$_ENV['DB_PASSWORD'] = \$password;
        
        putenv('DB_CONNECTION=mysql');
        putenv('DB_HOST=' . \$host);
        putenv('DB_PORT=' . \$port);
        putenv('DB_DATABASE=' . \$database);
        putenv('DB_USERNAME=' . \$username);
        putenv('DB_PASSWORD=' . \$password);
    } catch (PDOException \$e) {
        // Connection failed, will try the next method
    }
} 

// If DATABASE_URL failed, try internal Railway networking (mysql.railway.internal)
if (!isset(\$pdo)) {
    try {
        // Get database settings from environment variables
        \$internal_host = 'mysql.railway.internal';
        \$port = getenv('MYSQLPORT') ?: '3306';
        \$database = getenv('MYSQLDATABASE') ?: 'railway';
        \$username = getenv('MYSQLUSER') ?: 'root';
        \$password = getenv('MYSQLPASSWORD') ?: '';
        
        \$dsn = "mysql:host={\$internal_host};port={\$port};dbname={\$database}";
        \$pdo = new PDO(\$dsn, \$username, \$password, array(PDO::ATTR_TIMEOUT => 2));
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set environment variables for internal connection
        \$_ENV['DB_CONNECTION'] = 'mysql';
        \$_ENV['DB_HOST'] = \$internal_host;
        \$_ENV['DB_PORT'] = \$port;
        \$_ENV['DB_DATABASE'] = \$database;
        \$_ENV['DB_USERNAME'] = \$username;
        \$_ENV['DB_PASSWORD'] = \$password;
        
        putenv('DB_CONNECTION=mysql');
        putenv('DB_HOST=' . \$internal_host);
        putenv('DB_PORT=' . \$port);
        putenv('DB_DATABASE=' . \$database);
        putenv('DB_USERNAME=' . \$username);
        putenv('DB_PASSWORD=' . \$password);
    } catch (PDOException \$e) {
        // Internal connection failed, will try the external hostname
    }
}

// If internal connection failed, try our known external connection
if (!isset(\$pdo)) {
    try {
        \$external_host = 'yamabiko.proxy.rlwy.net';
        \$external_port = '52501';
        \$database = 'railway';
        \$username = 'root';
        \$password = getenv('MYSQLPASSWORD');
        
        \$dsn = "mysql:host={\$external_host};port={\$external_port};dbname={\$database}";
        \$pdo = new PDO(\$dsn, \$username, \$password, array(PDO::ATTR_TIMEOUT => 2));
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
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
        // If this fails too, we're out of luck
    }
}

// Configure global database settings for Laravel
\$GLOBALS['db_config_override'] = [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => getenv('DATABASE_URL') ?: '',
            'host' => getenv('DB_HOST') ?: 'yamabiko.proxy.rlwy.net',
            'port' => getenv('DB_PORT') ?: '52501',
            'database' => getenv('DB_DATABASE') ?: 'railway',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
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