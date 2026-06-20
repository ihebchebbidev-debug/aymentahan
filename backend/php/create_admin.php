<?php
/**
 * Admin User Creator
 * Allows manual creation of admin user with custom credentials
 * Usage: POST to this file with JSON body containing username, password, email, fullName
 */

// Use same DB as the CRM API (config.php)
require_once __DIR__ . '/config.php';

// Accept both GET (form) and POST (JSON)
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Show HTML form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Create Admin User</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                margin-top: 0;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                color: #555;
            }
            input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
            }
            input:focus {
                outline: none;
                border-color: #0066cc;
                box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
            }
            button {
                background: #0066cc;
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                width: 100%;
            }
            button:hover {
                background: #0052a3;
            }
            button:active {
                transform: scale(0.98);
            }
            .info {
                background: #e7f3ff;
                border-left: 4px solid #0066cc;
                padding: 12px;
                margin-bottom: 20px;
                border-radius: 4px;
                font-size: 14px;
                color: #003366;
            }
            .warning {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 12px;
                margin-top: 20px;
                border-radius: 4px;
                font-size: 14px;
                color: #856404;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔐 Create Admin User</h1>
            
            <div class="info">
                <strong>ℹ️ Info:</strong> Use this form to create a new administrator account with custom credentials.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" placeholder="e.g., AymenAdmin" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="e.g., admin@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="fullName">Full Name:</label>
                    <input type="text" id="fullName" name="fullName" placeholder="e.g., Administrator" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" placeholder="Enter secure password" required>
                </div>
                
                <button type="submit">✓ Create Admin User</button>
            </form>
            
            <div class="warning">
                <strong>⚠️ Warning:</strong> This script should be deleted after creating the admin user for security reasons.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($method === 'POST') {
    // Parse input
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'application/json') !== false) {
        // JSON POST
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $fullName = $input['fullName'] ?? '';
        $password = $input['password'] ?? '';
    } else {
        // Form POST
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $fullName = $_POST['fullName'] ?? '';
        $password = $_POST['password'] ?? '';
    }
    
    // Validate input
    $errors = [];
    
    if (empty($username)) $errors[] = 'Username is required';
    if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters';
    if (strlen($username) > 100) $errors[] = 'Username must be less than 100 characters';
    
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (strlen($email) > 120) $errors[] = 'Email must be less than 120 characters';
    
    if (empty($fullName)) $errors[] = 'Full name is required';
    if (strlen($fullName) > 255) $errors[] = 'Full name must be less than 255 characters';
    
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if (strlen($password) > 200) $errors[] = 'Password must be less than 200 characters';
    
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
        exit;
    }
    
    try {
        $pdo = (new Database())->getConnection();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'message' => $e->getMessage(),
        ]);
        exit;
    }
    
    // Check if table exists
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'crminternet_users'");
        if ($check->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Table not found',
                'message' => 'crminternet_users table does not exist. Please run setup.php first.'
            ]);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database query failed',
            'message' => $e->getMessage()
        ]);
        exit;
    }
    
    // Check if username already exists
    try {
        $check_stmt = $pdo->prepare('SELECT id FROM crminternet_users WHERE username = ? OR email = ? LIMIT 1');
        $check_stmt->execute([$username, $email]);
        if ($check_stmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'User already exists',
                'message' => 'Username or email already exists in database'
            ]);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database query failed',
            'message' => $e->getMessage()
        ]);
        exit;
    }
    
    // Hash password with bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Generate UUID v4
    $admin_uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    // Insert admin user
    try {
        $insert_sql = "
            INSERT INTO crminternet_users 
            (id, username, email, full_name, password_hash, role, team, active, must_change_password, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'Administrateur', NULL, TRUE, FALSE, NOW(), NOW())
        ";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([$admin_uuid, $username, $email, $fullName, $hashed_password]);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Admin user created successfully',
            'user' => [
                'id' => $admin_uuid,
                'username' => $username,
                'email' => $email,
                'fullName' => $fullName,
                'role' => 'Administrateur',
                'active' => true
            ],
            'credentials' => [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'warning' => 'Change password immediately after first login!'
            ]
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create admin user',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'Method not allowed'
]);
?>
