<?php
/**
 * Fix URL Settings for Faveo
 * This script updates the URL settings in the database to match the Railway deployment URL
 */

// Set display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security check - prevent unauthorized access
$password = $_POST['auth_password'] ?? $_GET['key'] ?? '';
$stored_password = getenv('ADMIN_PASSWORD') ?? 'install-faveo';
$authorized = ($password === $stored_password);

// Store results
$message = '';
$success = false;

// Function to connect to the database using various methods
function get_database_connection() {
    try {
        // Try Railway's internal networking first
        $host = 'mysql.railway.internal';
        $port = getenv('MYSQLPORT') ?: '3306';
        $database = getenv('MYSQLDATABASE') ?: 'railway';
        $username = getenv('MYSQLUSER') ?: 'root';
        $password = getenv('MYSQLPASSWORD') ?: '';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database}";
        $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // If internal connection fails, try external connection
        try {
            // This uses the known external hostname from the project structure doc
            $host = 'yamabiko.proxy.rlwy.net';
            $port = '52501'; // This might change, so ideally we'd get it from env
            $database = 'railway';
            $username = 'root';
            $password = getenv('MYSQLPASSWORD') ?: '';
            
            $dsn = "mysql:host={$host};port={$port};dbname={$database}";
            $pdo = new PDO($dsn, $username, $password, array(PDO::ATTR_TIMEOUT => 3));
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            // If all attempts fail, throw the exception
            throw $e;
        }
    }
}

// Check current settings and update URL if authorized and requested
$current_url = '';
$current_settings = null;

if ($authorized) {
    try {
        $pdo = get_database_connection();
        
        // Get current settings
        $stmt = $pdo->query("SELECT * FROM settings_system");
        $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_settings) {
            $current_url = $current_settings['url'];
        }
        
        // Update URL if submitted
        if (isset($_POST['new_url']) && !empty($_POST['new_url'])) {
            $new_url = trim($_POST['new_url']);
            
            // Make sure URL doesn't have port or trailing slash
            $new_url = preg_replace('/:[0-9]+/', '', $new_url);
            $new_url = rtrim($new_url, '/');
            
            $stmt = $pdo->prepare("UPDATE settings_system SET url = :url");
            $result = $stmt->execute([':url' => $new_url]);
            
            if ($result) {
                $message = "<p class='success'>URL updated successfully from '{$current_url}' to '{$new_url}'</p>";
                $current_url = $new_url;
                $success = true;
            } else {
                $message = "<p class='error'>Failed to update URL</p>";
            }
        }
    } catch (PDOException $e) {
        $message = "<p class='error'>Database error: " . $e->getMessage() . "</p>";
    }
}

// Auto-detect Railway URL
$detected_url = '';
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 
                $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $detected_url = $protocol . $_SERVER['HTTP_HOST'];
    $detected_url = preg_replace('/:[0-9]+/', '', $detected_url); // Remove any port
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo URL Settings Fix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1, h2, h3 {
            color: #336699;
        }
        .box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .warning {
            color: orange;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        button, input[type="submit"] {
            background: #336699;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover, input[type="submit"]:hover {
            background: #264d73;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo URL Settings Fix</h1>
        
        <?php if (!$authorized): ?>
        <div class="box">
            <h2>Authentication Required</h2>
            <p>Please enter the admin password to access this tool.</p>
            <form method="post">
                <div class="form-group">
                    <label for="auth_password">Password:</label>
                    <input type="password" id="auth_password" name="auth_password" required>
                </div>
                <button type="submit">Authenticate</button>
            </form>
        </div>
        <?php else: ?>
        
        <?php if (!empty($message)): ?>
        <div class="box">
            <h2>Result</h2>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <h2>Current URL Settings</h2>
            <?php if ($current_settings): ?>
            <p>Current URL in database: <strong><?php echo htmlspecialchars($current_url); ?></strong></p>
            <p>APP_URL environment variable: <strong><?php echo htmlspecialchars(getenv('APP_URL') ?: 'Not set'); ?></strong></p>
            <p>Auto-detected URL: <strong><?php echo htmlspecialchars($detected_url); ?></strong></p>
            
            <?php if (strpos($current_url, ':8080') !== false): ?>
            <p class="warning">⚠️ Your current URL contains port 8080 which is likely causing redirection issues.</p>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                <div class="form-group">
                    <label for="new_url">New URL (without port or trailing slash):</label>
                    <input type="text" id="new_url" name="new_url" value="<?php echo htmlspecialchars($detected_url ?: 'https://vibe-faveo-production.up.railway.app'); ?>" required>
                </div>
                <button type="submit">Update URL</button>
            </form>
            <?php else: ?>
            <p class="error">Could not retrieve current settings from database.</p>
            <?php endif; ?>
        </div>
        
        <div class="box">
            <h2>Common Issues</h2>
            <ul>
                <li>The <strong>:8080</strong> port in URLs causes problems in production environments</li>
                <li>The URL in the database settings takes precedence over environment variables</li>
                <li>After fixing, you might need to clear your browser cache or use incognito mode</li>
                <li>The application may redirect to incorrect URLs if settings mismatch</li>
            </ul>
        </div>
        
        <div class="box">
            <h2>Navigation</h2>
            <ul>
                <li><a href="diagnose-facade.php">Diagnostic Tool</a></li>
                <li><a href="install-dependencies.php">Install Dependencies</a></li>
                <li><a href="create-admin.php">Create/Update Admin</a></li>
                <li><a href="fix-permissions.php">Fix Permissions</a></li>
                <li><a href="/public">Go to Faveo</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 