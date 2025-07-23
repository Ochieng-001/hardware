<?php
require_once 'config.php';

$error = '';
$success = '';

// Check if admin is already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle login
if ($_POST && isset($_POST['login'])) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check admin credentials - FIXED: Use separate parameters
        $db->query("SELECT * FROM admins WHERE username = :username OR email = :email");
        $db->bind(':username', $username);
        $db->bind(':email', $username);
        $admin = $db->single();
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_email'] = $admin['email'];
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Lab Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <!-- Back to main site -->
            <div class="login-card">
                <div class="login-header">
                    <i class="fas fa-user-shield"></i>
                    <h2>Admin Login</h2>
                    <p class="mb-0 text-muted">Hardware Lab Management System</p>
                </div>
                
                <div class="login-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" name="username" required 
                                       placeholder="Enter username or email" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" name="password" required 
                                       placeholder="Enter password">
                            </div>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-login w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to Admin Panel
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Contact system administrator if you need assistance
                        </small>
                    </div>
                </div>
            </div>
        </div>
                             <!-- Back to main site -->
                    <div class="text-center mb-4">
                        <a href="index.php" class="back-link">
                            <i class="fas fa-arrow-left me-2"></i>Back to Student Portal
                        </a>
                    </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>