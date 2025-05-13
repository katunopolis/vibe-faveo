<?php
/**
 * Direct Database Migration for Faveo
 * This script performs database setup directly without using artisan commands
 * Use when artisan commands fail with error code 255
 */

// Start timing
$startTime = microtime(true);

// Set error reporting and increase memory limit
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutes execution time

// Output header
echo "<html><head><title>Faveo Direct Database Setup</title>";
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
    h1, h2 { color: #336699; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .step { margin-bottom: 20px; padding: 10px; border-left: 4px solid #ccc; }
    .step h3 { margin-top: 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";
echo "</head><body>";
echo "<h1>Faveo Direct Database Setup</h1>";
echo "<p>This script performs database setup directly without using artisan commands</p>";

// Security warning
echo "<div style='background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 20px; border-radius: 5px;'>
<strong>Security Notice:</strong> This script allows database initialization without authentication. 
For security reasons, please delete this file after you've successfully set up your Faveo installation.
</div>";

// Include our memory-only database helper
$SKIP_HEADER = true; // Skip HTML output from the helper
try {
    if (file_exists(__DIR__ . '/memory-only-fix.php')) {
        $db_config = include __DIR__ . '/memory-only-fix.php';
    } else {
        die("<p class='error'>memory-only-fix.php file not found. Please create it first.</p>");
    }
    
    if (!$db_config['success']) {
        die("<p class='error'>Error connecting to database: " . $db_config['message'] . "</p>");
    }
} catch (Exception $e) {
    die("<p class='error'>Failed to load database configuration: " . $e->getMessage() . "</p>");
}

// Get PDO connection from the config
$pdo = $db_config['pdo'];
if (!$pdo) {
    die("<p class='error'>No active database connection available</p>");
}

// Database connection info
echo "<h2>Database Connection Information</h2>";
echo "<p>Connected to <strong>{$db_config['connection']['host']}:{$db_config['connection']['port']}</strong> as <strong>{$db_config['connection']['username']}</strong></p>";
echo "<p>Database: <strong>{$db_config['connection']['database']}</strong></p>";
echo "<p>Connection source: <strong>{$db_config['connection']['method']}</strong></p>";

// Generate a secure random app key
function generateAppKey() {
    $key = base64_encode(random_bytes(32));
    return "base64:$key";
}

// Function to execute a SQL query and handle errors
function executeSql($pdo, $sql, $description = "SQL Query") {
    echo "<div class='step'>";
    echo "<h3>$description</h3>";
    echo "<pre>$sql</pre>";
    
    try {
        $result = $pdo->exec($sql);
        if ($result !== false) {
            echo "<p class='success'>Query executed successfully</p>";
            return true;
        } else {
            $error = $pdo->errorInfo();
            if ($error[0] !== '00000') {
                echo "<p class='error'>Query failed: [{$error[0]}] {$error[1]} - {$error[2]}</p>";
                return false;
            }
            echo "<p class='success'>Query executed (no rows affected)</p>";
            return true;
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Exception: " . $e->getMessage() . "</p>";
        echo "<p class='error'>Code: " . $e->getCode() . "</p>";
        if ($e->errorInfo) {
            echo "<p class='error'>SQL State: " . $e->errorInfo[0] . "</p>";
        }
        return false;
    }
    
    echo "</div>";
}

// Function to check if a table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to create a minimal users table
function createUsersTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `users` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `first_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `last_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `gender` tinyint(1) NOT NULL,
        `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `ban` tinyint(1) NOT NULL DEFAULT '0',
        `password` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
        `active` int(11) NOT NULL,
        `is_delete` tinyint(1) NOT NULL DEFAULT '0',
        `ext` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
        `country_code` int(11) DEFAULT 0,
        `phone_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
        `mobile` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `agent_sign` text COLLATE utf8_unicode_ci,
        `account_type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `account_status` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `assign_group` int(10) UNSIGNED DEFAULT NULL,
        `primary_dpt` int(10) UNSIGNED DEFAULT NULL,
        `agent_tzone` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `daylight_save` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `limit_access` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `directory_listing` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `vacation_mode` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `company` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `role` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `internal_note` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `profile_pic` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        `user_language` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `users_email_unique` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    
    return executeSql($pdo, $sql, "Creating users table");
}

// Function to create a minimal settings table
function createSettingsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `settings_system` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `status` tinyint(1) NOT NULL,
        `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `department` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `page_size` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `log_level` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `purge_log` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `name_format` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `time_farmat` int(11) NOT NULL,
        `date_format` int(11) NOT NULL,
        `date_time_format` int(11) NOT NULL,
        `day_date_time` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `time_zone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `content` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    
    return executeSql($pdo, $sql, "Creating settings_system table");
}

// Function to create user_role table
function createUserRoleTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `user_role` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `role_id` int(10) UNSIGNED NOT NULL,
        `user_id` int(10) UNSIGNED NOT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    
    return executeSql($pdo, $sql, "Creating user_role table");
}

// Function to create roles table
function createRolesTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `roles` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `permissions` text COLLATE utf8_unicode_ci,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    
    return executeSql($pdo, $sql, "Creating roles table");
}

// Function to create a minimal admin user
function createAdminUser($pdo) {
    // First check if admin user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@example.com'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "<p class='warning'>Admin user already exists</p>";
        return true;
    }
    
    // Generate a secure password hash
    $password = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
    $now = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO `users` 
        (`user_name`, `first_name`, `last_name`, `gender`, `email`, `password`, 
        `active`, `account_type`, `account_status`, `ext`, `country_code`, `phone_number`,
        `created_at`, `updated_at`) 
        VALUES 
        ('admin', 'System', 'Administrator', 1, 'admin@example.com', '$password', 
        1, 'admin', 'active', '', 0, '', '$now', '$now')";
    
    if (executeSql($pdo, $sql, "Creating admin user")) {
        echo "<div class='step'>";
        echo "<h3>Admin User Created</h3>";
        echo "<p>Username: <strong>admin@example.com</strong></p>";
        echo "<p>Password: <strong>admin123</strong></p>";
        echo "<p class='warning'>IMPORTANT: Change this password immediately after login!</p>";
        echo "</div>";
        return true;
    }
    
    return false;
}

// Function to create admin role
function createAdminRole($pdo) {
    // First check if role already exists
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin'");
    $stmt->execute();
    $roleId = $stmt->fetchColumn();
    
    if (!$roleId) {
        $permissions = json_encode(["all" => true]);
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `roles` (`name`, `permissions`, `created_at`, `updated_at`)
                VALUES ('admin', '$permissions', '$now', '$now')";
        
        if (!executeSql($pdo, $sql, "Creating admin role")) {
            return false;
        }
        
        // Get the inserted role ID
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'admin'");
        $stmt->execute();
        $roleId = $stmt->fetchColumn();
    } else {
        echo "<p class='warning'>Admin role already exists with ID $roleId</p>";
    }
    
    // Now assign the role to the admin user if it exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@example.com'");
        $stmt->execute();
        $userId = $stmt->fetchColumn();
        
        if (!$userId) {
            echo "<p class='warning'>Admin user not found, skipping role assignment</p>";
            return true; // Not a failure case, the user creation itself would have already reported an error
        }
        
        if ($roleId) {
            // Check if the role is already assigned
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_role WHERE role_id = ? AND user_id = ?");
            $stmt->execute([$roleId, $userId]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                echo "<p class='warning'>Role already assigned to user</p>";
                return true;
            }
            
            $now = date('Y-m-d H:i:s');
            $sql = "INSERT INTO `user_role` (`role_id`, `user_id`, `created_at`, `updated_at`)
                    VALUES ($roleId, $userId, '$now', '$now')";
            
            return executeSql($pdo, $sql, "Assigning admin role to user");
        } else {
            echo "<p class='error'>Could not find or create admin role</p>";
            return false;
        }
    } catch (Exception $e) {
        echo "<p class='error'>Error in role assignment: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Function to create migrations table
function createMigrationsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        `batch` int(11) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    
    return executeSql($pdo, $sql, "Creating migrations table");
}

// Function to initialize system settings
function initializeSettings($pdo) {
    // First check if settings already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings_system");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "<p class='warning'>System settings already exist</p>";
        return true;
    }
    
    // Try to detect current URL
    $url = 'http://localhost';
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $url = $protocol . $_SERVER['HTTP_HOST'];
    } else if (getenv('APP_URL')) {
        $url = getenv('APP_URL');
    } else if (getenv('RAILWAY_STATIC_URL')) {
        $url = getenv('RAILWAY_STATIC_URL');
    }
    
    $now = date('Y-m-d H:i:s');
    $sql = "INSERT INTO `settings_system` 
        (`status`, `url`, `name`, `department`, `page_size`, `log_level`, 
        `purge_log`, `name_format`, `time_farmat`, `date_format`, 
        `date_time_format`, `day_date_time`, `time_zone`, `content`, `created_at`, `updated_at`) 
        VALUES 
        (1, '$url', 'Faveo HELPDESK', 'Support', '10', 'error', 
        'never', 'first_last', 12, 1, 
        1, 'date_first', 'UTC', 'application/json', '$now', '$now')";
    
    return executeSql($pdo, $sql, "Initializing system settings");
}

// Function to create a simple database version log table
function createVersionTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `faveo_version` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `version` varchar(50) NOT NULL,
        `description` text,
        `installed_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if (executeSql($pdo, $sql, "Creating version table")) {
        $version = "1.0.0";
        $description = "Direct migration installation";
        $sql = "INSERT INTO `faveo_version` (`version`, `description`) VALUES ('$version', '$description')";
        return executeSql($pdo, $sql, "Recording installation version");
    }
    
    return false;
}

// Main execution flow
echo "<h2>Running Direct Database Setup</h2>";

try {
    // 1. Create migration tracking table
    createMigrationsTable($pdo);
    
    // 2. Create essential tables
    createUsersTable($pdo);
    createSettingsTable($pdo);
    createRolesTable($pdo);
    createUserRoleTable($pdo);
    
    // 3. Create admin user and role
    createAdminUser($pdo);
    createAdminRole($pdo);
    
    // 4. Initialize system settings
    initializeSettings($pdo);
    
    // 5. Create version tracking
    createVersionTable($pdo);
    
    // 6. Show tables created
    echo "<div class='step'>";
    echo "<h3>Database Tables Created</h3>";
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Found " . count($tables) . " tables:</p>";
        
        if (count($tables) > 0) {
            echo "<table border='1'>";
            echo "<tr><th>Table Name</th><th>Status</th></tr>";
            
            foreach ($tables as $table) {
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td class='success'>Created</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Error fetching tables: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>Error during database setup: " . $e->getMessage() . "</p>";
}

echo "<h2>Next Steps</h2>";
echo "<p>If the basic setup completed successfully, you should continue with these steps:</p>";
echo "<ol>
    <li>Log in to Faveo using the admin credentials provided</li>
    <li>Complete the setup through the web interface</li>
    <li>Change the default admin password immediately</li>
    <li>DELETE THIS SCRIPT for security reasons</li>
</ol>";

// Display completion message
echo "<div class='step'>";
echo "<h2>Setup Complete</h2>";
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);
echo "<p>Execution time: $executionTime seconds</p>";

echo "<p><a href='/public' class='success'>Go to Faveo →</a></p>";

echo "</body></html>"; 