<?php
/**
 * Faveo Utilities Index
 * 
 * This script provides links to all utility scripts for Faveo administration.
 */

// Security check - prevent unauthorized access
$password = $_POST['auth_password'] ?? $_GET['key'] ?? '';
$stored_password = getenv('ADMIN_PASSWORD') ?? 'install-faveo';
$authorized = ($password === $stored_password);

// Store results
$message = '';

// Define all utility scripts
$utilities = [
    'Health and Diagnostics' => [
        [
            'name' => 'Health Check',
            'description' => 'Simple health check endpoint for Railway',
            'path' => 'health/health.php'
        ],
        [
            'name' => 'Comprehensive Diagnostics',
            'description' => 'Comprehensive system diagnostics for troubleshooting',
            'path' => 'health/diagnostics.php'
        ]
    ],
    'URL and Configuration Fixes' => [
        [
            'name' => 'URL Fixer',
            'description' => 'Fix URL redirect issues in the database and configuration',
            'path' => 'fixes/url-fix.php'
        ],
        [
            'name' => 'Bootstrap Fixer',
            'description' => 'Fix Laravel bootstrap issues including permissions and configuration',
            'path' => 'fixes/bootstrap-fix.php'
        ]
    ],
    'Database Utilities' => [
        [
            'name' => 'Database Setup',
            'description' => 'Configure and test database connection',
            'path' => 'database/database-setup.php'
        ]
    ],
    'Admin Utilities' => [
        [
            'name' => 'Password Reset',
            'description' => 'Reset admin passwords or create a new admin user',
            'path' => 'admin/reset-password.php'
        ]
    ]
];

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Faveo Utilities</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 900px;
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
        .utility-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            grid-gap: 20px;
            margin-top: 20px;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        .card h3 {
            margin-top: 0;
            color: #336699;
        }
        .card p {
            color: #666;
            margin-bottom: 15px;
        }
        .card a {
            display: inline-block;
            background: #336699;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
        }
        .card a:hover {
            background: #264d73;
        }
        .category {
            margin-bottom: 30px;
        }
        .back-to-app {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #336699;
            font-weight: bold;
        }
        .back-to-app:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Faveo Utilities Dashboard</h1>
        
        <?php if (!$authorized): ?>
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
            <p><strong>Note:</strong> All utilities require authentication with the admin password.</p>
        </div>
        
        <?php foreach ($utilities as $category => $tools): ?>
        <div class="category">
            <h2><?php echo htmlspecialchars($category); ?></h2>
            <div class="utility-cards">
                <?php foreach ($tools as $tool): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($tool['name']); ?></h3>
                    <p><?php echo htmlspecialchars($tool['description']); ?></p>
                    <a href="<?php echo htmlspecialchars($tool['path']) . '?key=' . urlencode($password); ?>">Open Tool</a>
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