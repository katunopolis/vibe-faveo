<?php
// Direct database setup script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Direct Database Setup</h1>";

// Get Railway environment variables
$mysql_host = getenv('MYSQLHOST');
$mysql_port = getenv('MYSQLPORT');
$mysql_database = getenv('MYSQLDATABASE');
$mysql_user = getenv('MYSQLUSER');
$mysql_password = getenv('MYSQLPASSWORD');

echo "<h2>Environment Variables</h2>";
echo "<p>MYSQLHOST: " . ($mysql_host ?: "Not set") . "</p>";
echo "<p>MYSQLPORT: " . ($mysql_port ?: "Not set") . "</p>";
echo "<p>MYSQLDATABASE: " . ($mysql_database ?: "Not set") . "</p>";
echo "<p>MYSQLUSER: " . ($mysql_user ?: "Not set") . "</p>";
echo "<p>MYSQLPASSWORD: " . ($mysql_password ? "Set (hidden)" : "Not set") . "</p>";

// Define potential database hosts to try
$hosts = [
    $mysql_host,
    'mysql',
    'db',
    'database',
    'localhost',
    '127.0.0.1',
    // You can add more possible hostnames here
];

// Filter out empty hosts
$hosts = array_filter($hosts);

// Try to connect to each host
echo "<h2>Connection Attempts</h2>";
$success = false;
$working_host = null;

foreach ($hosts as $host) {
    if (empty($host)) continue;

    echo "<h3>Trying $host</h3>";
    try {
        $dsn = "mysql:host=$host;port=" . ($mysql_port ?: '3306') . ";dbname=" . ($mysql_database ?: 'railway');
        echo "<p>DSN: $dsn</p>";
        $conn = new PDO($dsn, $mysql_user, $mysql_password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p style='color:green'>✓ Connection successful!</p>";
        $success = true;
        $working_host = $host;
        $working_connection = $conn;
        break;
    } catch (PDOException $e) {
        echo "<p style='color:red'>✗ Connection failed: " . $e->getMessage() . "</p>";
    }
}

// If we found a working connection, check and create tables
if ($success) {
    echo "<h2>Database Setup</h2>";
    echo "<p>Successfully connected to MySQL at host '$working_host'</p>";
    
    // Check if tables exist
    $stmt = $working_connection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Existing Tables</h3>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No tables exist in the database. Creating test table...</p>";
        
        // Create a test table
        try {
            $working_connection->exec("
                CREATE TABLE faveo_test (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            echo "<p style='color:green'>✓ Created test table 'faveo_test'</p>";
            
            // Insert some data
            $working_connection->exec("
                INSERT INTO faveo_test (name) 
                VALUES ('Test 1'), ('Test 2'), ('Railway Test')
            ");
            echo "<p style='color:green'>✓ Inserted test data</p>";
            
            // Verify data
            $result = $working_connection->query("SELECT * FROM faveo_test");
            $data = $result->fetchAll(PDO::FETCH_ASSOC);
            echo "<h4>Test Data</h4>";
            echo "<pre>" . print_r($data, true) . "</pre>";
        } catch (PDOException $e) {
            echo "<p style='color:red'>✗ Error creating test table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Create a PHP snippet that can be included in your application to bypass .env
    echo "<h3>PHP Connection Snippet</h3>";
    echo "<p>Add this to your application to bypass .env file for database connections:</p>";
    
    $snippet = '<?php
// Direct database connection configuration
$_ENV["DB_CONNECTION"] = "mysql";
$_ENV["DB_HOST"] = "' . $working_host . '";
$_ENV["DB_PORT"] = "' . ($mysql_port ?: '3306') . '";
$_ENV["DB_DATABASE"] = "' . ($mysql_database ?: 'railway') . '";
$_ENV["DB_USERNAME"] = "' . $mysql_user . '";
$_ENV["DB_PASSWORD"] = "' . $mysql_password . '";

// Force these variables into $_ENV and apache environment
putenv("DB_CONNECTION=mysql");
putenv("DB_HOST=' . $working_host . '");
putenv("DB_PORT=' . ($mysql_port ?: '3306') . '");
putenv("DB_DATABASE=' . ($mysql_database ?: 'railway') . '");
putenv("DB_USERNAME=' . $mysql_user . '");
putenv("DB_PASSWORD=' . $mysql_password . '");
?>';
    
    // Create the file
    $snippetPath = __DIR__ . '/db-config.php';
    file_put_contents($snippetPath, $snippet);
    
    echo "<pre>" . htmlspecialchars($snippet) . "</pre>";
    echo "<p style='color:green'>✓ This snippet has been saved to " . $snippetPath . "</p>";
    echo "<p>Include this file at the very start of your application to bypass .env file loading.</p>";
} else {
    echo "<h2 style='color:red'>✗ Could not connect to any database host</h2>";
    echo "<p>Please make sure your MySQL service is running and accessible from this container.</p>";
    echo "<p>You may need to:</p>";
    echo "<ul>";
    echo "<li>Verify the MySQL service is running in Railway</li>";
    echo "<li>Check that the environment variables are correctly set</li>";
    echo "<li>Ensure the network between this container and the MySQL service is properly configured</li>";
    echo "<li>Consider creating a direct TCP proxy or using Railway's 'Link' feature</li>";
    echo "</ul>";
}
?> 