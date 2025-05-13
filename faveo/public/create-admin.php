<?php
// Admin user creation script for Faveo on Railway
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Faveo Admin Creation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1000px; margin: 0 auto; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; border-radius: 4px; }
        p.error { color: #e74c3c; font-weight: bold; }
        p.success { color: #2ecc71; font-weight: bold; }
        p.warning { color: #f39c12; font-weight: bold; }
        code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
        form { background: #f9f9f9; padding: 20px; border-radius: 5px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type='text'], input[type='email'], input[type='password'] { 
            width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; 
        }
        button { background: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #2980b9; }
    </style>
</head>
<body>
    <h1>Faveo Admin Creation</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = 'mysql:host=mysql.railway.internal;port=3306;dbname=railway';
        $username = 'root';
        $password = getenv('MYSQLPASSWORD');
        
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $admin_email = $_POST['email'] ?? 'admin@example.com';
        $admin_password = $_POST['password'] ?? 'Admin@123';
        $admin_name = $_POST['name'] ?? 'System Admin';
        $admin_username = $_POST['username'] ?? 'admin';
        
        // Hash the password (using Laravel's bcrypt approach)
        $hashed_password = password_hash($admin_password, PASSWORD_BCRYPT, ['rounds' => 10]);
        
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            die("<p class='error'>Users table not found. Please run migrations first.</p>");
        }
        
        // Check the structure of the users table
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check if any users exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $user_count = $stmt->fetchColumn();
        
        if ($user_count > 0) {
            // Update the first user as admin
            $sql = "UPDATE users SET 
                    email = :email,
                    password = :password,
                    first_name = :name,
                    user_name = :username,
                    role = 'admin',
                    active = 1
                    WHERE id = 1";
            $stmt = $pdo->prepare($sql);
        } else {
            // Insert a new admin user
            $sql = "INSERT INTO users 
                    (email, password, first_name, user_name, role, active, created_at, updated_at) 
                    VALUES 
                    (:email, :password, :name, :username, 'admin', 1, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
        }
        
        $stmt->execute([
            ':email' => $admin_email,
            ':password' => $hashed_password,
            ':name' => $admin_name,
            ':username' => $admin_username
        ]);
        
        echo "<p class='success'>Admin user created/updated successfully!</p>";
        echo "<p><strong>Login Details:</strong></p>";
        echo "<p>Email: $admin_email</p>";
        echo "<p>Username: $admin_username</p>";
        echo "<p>Password: $admin_password</p>";
        echo "<p class='warning'><strong>Please save these credentials and change the password after login!</strong></p>";
        echo "<p><a href='/public'>Go to Faveo Login</a></p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>Database error: " . $e->getMessage() . "</p>";
    }
} else {
    // Check if we can connect to the database
    try {
        $dsn = 'mysql:host=mysql.railway.internal;port=3306;dbname=railway';
        $username = 'root';
        $password = getenv('MYSQLPASSWORD');
        
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            echo "<p class='error'>Users table not found. Please <a href='run-migrations.php'>run migrations</a> first.</p>";
        } else {
            echo "<p class='success'>Connected to database and found users table.</p>";
            
            // Check if any users exist
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $user_count = $stmt->fetchColumn();
            
            if ($user_count > 0) {
                echo "<p class='warning'>Found $user_count existing user(s). Creating a new admin will update the first user record.</p>";
            } else {
                echo "<p>No existing users found. This form will create a new admin user.</p>";
            }
            
            // Show the form
            echo "<form method='post'>
                <h2>Create Admin User</h2>
                <div>
                    <label for='name'>Admin Name:</label>
                    <input type='text' id='name' name='name' value='System Admin' required>
                </div>
                <div>
                    <label for='username'>Username:</label>
                    <input type='text' id='username' name='username' value='admin' required>
                </div>
                <div>
                    <label for='email'>Admin Email:</label>
                    <input type='email' id='email' name='email' value='admin@example.com' required>
                </div>
                <div>
                    <label for='password'>Admin Password:</label>
                    <input type='password' id='password' name='password' value='Admin@123' required>
                </div>
                <button type='submit'>Create Admin</button>
            </form>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>Database connection error: " . $e->getMessage() . "</p>";
        echo "<p>Please make sure your database is properly configured and <a href='run-migrations.php'>run migrations</a> first.</p>";
    }
}

echo "<h2>Navigation</h2>
<ul>
    <li><a href='run-migrations.php'>Run Migrations</a></li>
    <li><a href='repair-database.php'>Repair Database</a></li>
    <li><a href='fix-permissions.php'>Fix Permissions</a></li>
    <li><a href='/public'>Go to Faveo</a></li>
</ul>
</body>
</html>"; 