<?php
/**
 * Admin Password Reset Utility for Faveo
 * 
 * This script allows resetting admin passwords in the Faveo database
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
$results = [];
$message = '';
$success = false;

/**
 * Database connection function that tries multiple connection methods
 */
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
            // Get connection details from environment if available
            $host = getenv('MYSQLHOST') ?: 'yamabiko.proxy.rlwy.net';
            $port = getenv('MYSQLPORT') ?: '52501';
            $database = getenv('MYSQLDATABASE') ?: 'railway';
            $username = getenv('MYSQLUSER') ?: 'root';
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

/**
 * Get all admins from the database
 */
function get_admins() {
    try {
        $pdo = get_database_connection();
        
        // Check if users table exists
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('users', $tables)) {
            return [
                'success' => false,
                'message' => 'Users table not found in database'
            ];
        }
        
        // Get users with admin role
        $stmt = $pdo->query("SELECT id, email, first_name, last_name, user_name, role, active FROM users WHERE role = 'admin'");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'admins' => $admins
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Reset a user's password
 */
function reset_password($user_id, $new_password) {
    try {
        $pdo = get_database_connection();
        
        // Hash the password - Faveo uses Laravel's bcrypt by default
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Update the user's password
        $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
        $result = $stmt->execute([
            ':password' => $hashed_password,
            ':id' => $user_id
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Password updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No user found with that ID or password not changed'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Create a new admin user if none exist
 */
function create_admin($email, $username, $password, $first_name = 'Admin', $last_name = 'User') {
    try {
        $pdo = get_database_connection();
        
        // Check if users table exists
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('users', $tables)) {
            return [
                'success' => false,
                'message' => 'Users table not found in database'
            ];
        }
        
        // Check if admin already exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count > 0) {
            return [
                'success' => false,
                'message' => 'Admin user already exists. Use reset password instead.'
            ];
        }
        
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Create new admin user
        $stmt = $pdo->prepare("INSERT INTO users (
            email, password, user_name, first_name, last_name, 
            role, active, is_delete, email_verify, created_at, updated_at
        ) VALUES (
            :email, :password, :username, :first_name, :last_name,
            'admin', 1, 0, 1, NOW(), NOW()
        )");
        
        $result = $stmt->execute([
            ':email' => $email,
            ':password' => $hashed_password,
            ':username' => $username,
            ':first_name' => $first_name,
            ':last_name' => $last_name
        ]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Admin user created successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to create admin user'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

// Get admin users for display
$admins_result = null;
if ($authorized) {
    $admins_result = get_admins();
}

// Process the form
if ($authorized) {
    // Reset password
    if (isset($_POST['reset_password']) && isset($_POST['user_id']) && isset($_POST['new_password'])) {
        $user_id = $_POST['user_id'];
        $new_password = $_POST['new_password'];
        
        if (empty($new_password)) {
            $success = false;
            $message = 'Password cannot be empty';
        } else {
            $reset_result = reset_password($user_id, $new_password);
            $success = $reset_result['success'];
            $message = $reset_result['message'];
            
            if ($success) {
                // Refresh admins list
                $admins_result = get_admins();
            }
        }
    }
    
    // Create admin
    if (isset($_POST['create_admin'])) {
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $first_name = $_POST['first_name'] ?? 'Admin';
        $last_name = $_POST['last_name'] ?? 'User';
        
        if (empty($email) || empty($username) || empty($new_password)) {
            $success = false;
            $message = 'Email, username and password are required';
        } else {
            $create_result = create_admin($email, $username, $new_password, $first_name, $last_name);
            $success = $create_result['success'];
            $message = $create_result['message'];
            
            if ($success) {
                // Refresh admins list
                $admins_result = get_admins();
            }
        }
    }
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo Admin Password Reset</title>
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
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .warning {
            color: orange;
        }
        button, input[type="submit"] {
            background: #336699;
            color: white;
            border: none;
            padding: 8px 15px;
            margin-right: 5px;
            margin-bottom: 5px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover, input[type="submit"]:hover {
            background: #264d73;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .tab {
            display: none;
        }
        .tab.active {
            display: block;
        }
        .tab-buttons {
            margin-bottom: 20px;
        }
        .tab-button {
            background: #f2f2f2;
            border: 1px solid #ddd;
            padding: 8px 15px;
            margin-right: 5px;
            cursor: pointer;
            border-radius: 4px 4px 0 0;
        }
        .tab-button.active {
            background: #336699;
            color: white;
            border-color: #336699;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo Admin Password Reset</h1>
        
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
            <p class="<?php echo $success ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="box">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab('reset-tab')">Reset Password</button>
                <button class="tab-button" onclick="openTab('create-tab')">Create Admin</button>
            </div>
            
            <div id="reset-tab" class="tab active">
                <h2>Reset Admin Password</h2>
                
                <?php if (isset($admins_result) && $admins_result['success'] && !empty($admins_result['admins'])): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Active</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($admins_result['admins'] as $admin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($admin['id']); ?></td>
                        <td><?php echo htmlspecialchars($admin['user_name']); ?></td>
                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                        <td><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></td>
                        <td><?php echo $admin['active'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($admin['id']); ?>">
                                <div class="form-group" style="display: flex;">
                                    <input type="password" name="new_password" placeholder="New password" required style="margin-right: 5px;">
                                    <button type="submit" name="reset_password">Reset</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php elseif (isset($admins_result) && !$admins_result['success']): ?>
                <p class="error"><?php echo htmlspecialchars($admins_result['message']); ?></p>
                <p>You can create a new admin user if none exist.</p>
                <?php else: ?>
                <p>No admin users found in the database.</p>
                <p>Use the "Create Admin" tab to create a new admin user.</p>
                <?php endif; ?>
            </div>
            
            <div id="create-tab" class="tab">
                <h2>Create Admin User</h2>
                <p>Use this form to create a new admin user if none exist.</p>
                
                <form method="post">
                    <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" value="Admin">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" value="User">
                    </div>
                    
                    <button type="submit" name="create_admin">Create Admin User</button>
                </form>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        function openTab(tabId) {
            // Hide all tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Deactivate all tab buttons
            var buttons = document.getElementsByClassName('tab-button');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].classList.remove('active');
            }
            
            // Show the selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Activate the corresponding button
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html> 