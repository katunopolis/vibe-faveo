<?php
/**
 * Direct Database Setup for Faveo
 * This script bypasses Laravel's Artisan commands to create the basic database structure
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Output header
echo "<!DOCTYPE html>
<html>
<head>
    <title>Faveo Direct Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
        h1, h2, h3 { color: #336699; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .code { font-family: monospace; background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Faveo Direct Database Setup</h1>
    <div style='background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 20px; border-radius: 5px;'>
        <strong>Note:</strong> This script directly sets up the database without using Laravel's Artisan commands.
        This helps bypass the 'root facade' error.
    </div>";

// Database connection
try {
    $dsn = 'mysql:host=mysql.railway.internal;port=3306;dbname=railway';
    $username = 'root';
    $password = getenv('MYSQLPASSWORD');
    
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>✓ Connected to database successfully!</p>";
} catch (PDOException $e) {
    die("<p class='error'>Error connecting to database: " . $e->getMessage() . "</p>");
}

// Check if tables already exist
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<h2>Current Database Status</h2>";
echo "<p>Found " . count($tables) . " existing tables</p>";

if (count($tables) > 0) {
    echo "<details>
            <summary>Existing tables (click to expand)</summary>
            <ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>
          </details>";

    echo "<h3>Options</h3>";
    echo "<p>Do you want to proceed with one of these options?</p>";
    
    // Form for database actions
    echo "<form method='post'>";
    
    // Hidden field to indicate this is a form submission
    echo "<input type='hidden' name='action_submitted' value='1'>";
    
    // Option buttons
    echo "<button type='submit' name='action' value='keep' style='margin-right: 10px; padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;'>Keep Existing Tables</button>";
    echo "<button type='submit' name='action' value='drop' style='padding: 8px 15px; background-color: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;'>Drop All Tables</button>";
    
    echo "</form>";
    
    // Check if form was submitted
    if (isset($_POST['action_submitted'])) {
        $action = $_POST['action'] ?? 'keep';
        
        if ($action === 'drop') {
            echo "<h2>Dropping All Tables</h2>";
            
            // Temporarily disable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            foreach ($tables as $table) {
                try {
                    $pdo->exec("DROP TABLE `$table`");
                    echo "<p>Dropped table: $table</p>";
                } catch (PDOException $e) {
                    echo "<p class='error'>Error dropping table $table: " . $e->getMessage() . "</p>";
                }
            }
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            echo "<p class='success'>All tables dropped successfully!</p>";
            
            // Refresh list of tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            echo "<h2>Keeping Existing Tables</h2>";
            echo "<p>Proceeding with the existing database structure.</p>";
        }
    }
}

// Check if we need to create tables (if none exist or they were dropped)
if (count($tables) === 0) {
    echo "<h2>Setting Up Core Tables</h2>";
    
    // Schema for creating the core tables
    $schema = [
        "CREATE TABLE `users` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `first_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `last_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `password` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
            `active` int(1) NOT NULL DEFAULT '0',
            `is_delete` int(1) NOT NULL DEFAULT '0',
            `role` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'user',
            `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
        
        "CREATE TABLE `settings` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `company_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `website` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `phone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `logo` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
        
        "CREATE TABLE `tickets` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_number` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `user_id` int(10) UNSIGNED NOT NULL,
            `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `status` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'open',
            `priority` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'low',
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tickets_user_id_foreign` (`user_id`),
            CONSTRAINT `tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
        
        "CREATE TABLE `ticket_thread` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` int(10) UNSIGNED NOT NULL,
            `user_id` int(10) UNSIGNED NOT NULL,
            `body` text COLLATE utf8_unicode_ci NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ticket_thread_ticket_id_foreign` (`ticket_id`),
            KEY `ticket_thread_user_id_foreign` (`user_id`),
            CONSTRAINT `ticket_thread_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
            CONSTRAINT `ticket_thread_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
        
        "CREATE TABLE `emails` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `email_address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `email_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `sending_status` int(10) UNSIGNED NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
        
        "CREATE TABLE `templates` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `template_set_to_clone` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `active` int(11) NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"
    ];
    
    // Execute each CREATE TABLE statement
    foreach ($schema as $sql) {
        try {
            $pdo->exec($sql);
            // Extract table name for confirmation message
            preg_match('/CREATE TABLE `([^`]+)`/', $sql, $matches);
            $tableName = $matches[1] ?? 'unknown';
            echo "<p class='success'>Created table: $tableName</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>Error creating table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Create default admin user
    try {
        $password = password_hash('admin', PASSWORD_BCRYPT, ['rounds' => 10]);
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `users` 
                (`user_name`, `first_name`, `last_name`, `email`, `password`, `active`, `role`, `created_at`, `updated_at`) 
                VALUES 
                ('admin', 'System', 'Administrator', 'admin@example.com', :password, 1, 'admin', :created_at, :updated_at)";
                
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':created_at', $now);
        $stmt->bindParam(':updated_at', $now);
        $stmt->execute();
        
        echo "<p class='success'>Created default admin user (admin@example.com / admin)</p>";
        echo "<p class='warning'>Please change this password immediately after logging in!</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>Error creating admin user: " . $e->getMessage() . "</p>";
    }
    
    // Create default settings
    try {
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `settings` 
                (`company_name`, `website`, `phone`, `address`, `logo`, `created_at`, `updated_at`) 
                VALUES 
                ('Faveo Helpdesk', 'example.com', '123-456-7890', '123 Main St', 'logo.png', :created_at, :updated_at)";
                
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':created_at', $now);
        $stmt->bindParam(':updated_at', $now);
        $stmt->execute();
        
        echo "<p class='success'>Created default settings</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>Error creating settings: " . $e->getMessage() . "</p>";
    }
    
    // Check final results
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Setup Complete</h2>";
    echo "<p>Successfully created " . count($tables) . " tables:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
}

// Provide login instructions
echo "<h2>Next Steps</h2>";
echo "<p>Now that the database is set up, you can:</p>";
echo "<ol>";
echo "<li><a href='create-admin.php'>Create or modify the admin user</a></li>";
echo "<li><a href='fix-permissions.php'>Fix file permissions</a></li>";
echo "<li><a href='/public'>Go to Faveo (login with admin@example.com / admin if you used the default user)</a></li>";
echo "</ol>";

echo "</body>
</html>"; 