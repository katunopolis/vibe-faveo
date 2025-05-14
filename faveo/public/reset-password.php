<?php
/**
 * Password Reset Tool for Faveo
 * This script allows resetting the admin password with a properly hashed value
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

// Actually update the password if authorized and requested
if ($authorized && isset($_POST['new_password']) && !empty($_POST['new_password'])) {
    try {
        $pdo = get_database_connection();
        
        // Get the new password and hash it with bcrypt
        $new_password = $_POST['new_password'];
        $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Email we want to update (typically the admin email)
        $email = $_POST['email'] ?? 'admin@example.com';
        
        // Update the password in the database
        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
        $result = $stmt->execute([
            ':password' => $hash,
            ':email' => $email
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            $message = "<p class='success'>Password updated successfully for user: {$email}</p>";
            $success = true;
        } else {
            $message = "<p class='error'>No users found with email: {$email}</p>";
        }
    } catch (PDOException $e) {
        $message = "<p class='error'>Database error: " . $e->getMessage() . "</p>";
    }
}

// Check database connection and get users
$users = [];
$connection_status = '';
if ($authorized) {
    try {
        $pdo = get_database_connection();
        $stmt = $pdo->query("SELECT id, email, user_name, first_name, last_name FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $connection_status = "<p class='success'>Connected to database successfully</p>";
    } catch (PDOException $e) {
        $connection_status = "<p class='error'>Database connection error: " . $e->getMessage() . "</p>";
    }
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo Password Reset Tool</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo Password Reset Tool</h1>
        
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
            <h2>Reset Admin Password</h2>
            <?php echo $connection_status; ?>
            
            <form method="post">
                <input type="hidden" name="auth_password" value="<?php echo htmlspecialchars($password); ?>">
                
                <div class="form-group">
                    <label for="email">Select User:</label>
                    <select name="email" id="email">
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['email']); ?>">
                            <?php echo htmlspecialchars($user['email']); ?> 
                            (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <button type="submit">Update Password</button>
            </form>
            
            <?php if (!empty($users)): ?>
            <h3>Users in Database</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Name</th>
                </tr>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['user_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
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