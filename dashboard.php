<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = [
    'id' => $_SESSION['admin_id'],
    'username' => $_SESSION['admin_username'],
    'name' => $_SESSION['admin_name'],
    'role' => $_SESSION['admin_role'],
    'email' => $_SESSION['admin_email']
];

$error = '';
$success = '';

// Handle actions
if ($_POST) {
    // Update ticket status
    if (isset($_POST['update_ticket_status'])) {
        $ticket_id = $_POST['ticket_id'];
        $status = $_POST['status'];
        $assigned_to = $_POST['assigned_to'] ?: null;
        $scheduled_date = $_POST['scheduled_date'] ?: null;
        $scheduled_time = $_POST['scheduled_time'] ?: null;
        $comment = $_POST['comment'] ?: '';
        
        $db->query("UPDATE assistance_tickets SET status = :status, assigned_to = :assigned_to, scheduled_date = :scheduled_date, scheduled_time = :scheduled_time WHERE id = :ticket_id");
        $db->bind(':status', $status);
        $db->bind(':assigned_to', $assigned_to);
        $db->bind(':scheduled_date', $scheduled_date);
        $db->bind(':scheduled_time', $scheduled_time);
        $db->bind(':ticket_id', $ticket_id);
        $db->execute();
        
        // Add comment if provided
        if (!empty($comment)) {
            $db->query("INSERT INTO ticket_comments (ticket_id, admin_id, comment, is_internal) VALUES (:ticket_id, :admin_id, :comment, 0)");
            $db->bind(':ticket_id', $ticket_id);
            $db->bind(':admin_id', $admin['id']);
            $db->bind(':comment', $comment);
            $db->execute();
        }
        
        $success = 'Ticket updated successfully.';
    }
    
     // handle admin profile update
    if (isset($_POST['update_profile'])) {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $full_name = sanitizeInput($_POST['full_name']);
        
        if (empty($username) || empty($email) || empty($full_name)) {
            $error = 'Please fill in all fields.';
        } else {
            // Update admin profile
            $db->query("UPDATE admins SET username = :username, email = :email, full_name = :full_name WHERE id = :admin_id");
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $db->bind(':full_name', $full_name);
            $db->bind(':admin_id', $admin['id']);
            $db->execute();
            
            // Update session variables
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_name'] = $full_name;
            
            $success = 'Profile updated successfully.';
        }
    }

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } else {
            // Verify current password
            $db->query("SELECT password FROM admins WHERE id = :admin_id");
            $db->bind(':admin_id', $admin['id']);
            $result = $db->single();
            
            if ($result && password_verify($current_password, $result['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $db->query("UPDATE admins SET password = :password WHERE id = :admin_id");
                $db->bind(':password', $hashed_password);
                $db->bind(':admin_id', $admin['id']);
                $db->execute();
                
                $success = 'Password changed successfully.';
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }

    // handle adding new admin
    if (isset($_POST['add_admin'])) {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $full_name = sanitizeInput($_POST['full_name']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            // Check if username or email already exists
            $db->query("SELECT COUNT(*) as count FROM admins WHERE username = :username OR email = :email");
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $count = $db->single()['count'];
            
            if ($count > 0) {
                $error = 'Username or email already exists.';
            } else {
                // Insert new admin
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $db->query("INSERT INTO admins (username, email, full_name, password, role) VALUES (:username, :email, :full_name, :password, :role)");
                $db->bind(':username', $username);
                $db->bind(':email', $email);
                $db->bind(':full_name', $full_name);
                $db->bind(':password', $hashed_password);
                $db->bind(':role', $role);
                $db->execute();
                
                $success = 'New admin added successfully.';
            }
        }
    }

    // Update borrowing request status
    if (isset($_POST['update_borrowing_status'])) {
        $request_id = $_POST['request_id'];
        $status = $_POST['status'];
        $notes = $_POST['notes'] ?: '';
        
        $update_query = "UPDATE borrowing_requests SET status = :status, notes = :notes";
        $params = [':status' => $status, ':notes' => $notes, ':request_id' => $request_id];
        
        if ($status === 'approved') {
            $update_query .= ", approved_by = :approved_by, approved_at = NOW()";
            $params[':approved_by'] = $admin['id'];
        } elseif ($status === 'active') {
            $update_query .= ", borrowed_at = NOW(), due_date = :due_date";
            $params[':due_date'] = $_POST['due_date'];
        }
        
        $update_query .= " WHERE id = :request_id";
        
        $db->query($update_query);
        foreach ($params as $param => $value) {
            $db->bind($param, $value);
        }
        $db->execute();
        
        // Update equipment availability if approved or returned
        if ($status === 'active' || $status === 'returned') {
            $db->query("SELECT equipment_id, quantity_requested FROM borrowing_requests WHERE id = :request_id");
            $db->bind(':request_id', $request_id);
            $request = $db->single();
            
            if ($status === 'active') {
                // Decrease available quantity
                $db->query("UPDATE equipment SET quantity_available = quantity_available - :quantity WHERE id = :equipment_id");
            } else {
                // Increase available quantity
                $db->query("UPDATE equipment SET quantity_available = quantity_available + :quantity WHERE id = :equipment_id");
            }
            $db->bind(':quantity', $request['quantity_requested']);
            $db->bind(':equipment_id', $request['equipment_id']);
            $db->execute();
        }
        
        $success = 'Borrowing request updated successfully.';
    }
    
    // Add new equipment
    if (isset($_POST['add_equipment'])) {
        $category_id = $_POST['category_id'];
        $name = $_POST['name'];
        $model = $_POST['model'];
        $serial_number = $_POST['serial_number'];
        $total_quantity = $_POST['total_quantity'];
        
        $db->query("INSERT INTO equipment (category_id, name, model, serial_number, total_quantity, quantity_available) VALUES (:category_id, :name, :model, :serial_number, :total_quantity, :quantity_available)");
        $db->bind(':category_id', $category_id);
        $db->bind(':name', $name);
        $db->bind(':model', $model);
        $db->bind(':serial_number', $serial_number);
        $db->bind(':total_quantity', $total_quantity);
        $db->bind(':quantity_available', $total_quantity);
        $db->execute();
        
        $success = 'Equipment added successfully.';
    }
    
    // Update equipment status
    if (isset($_POST['update_equipment_status'])) {
        $equipment_id = $_POST['equipment_id'];
        $status = $_POST['equipment_status'];
        
        $db->query("UPDATE equipment SET status = :status WHERE id = :equipment_id");
        $db->bind(':status', $status);
        $db->bind(':equipment_id', $equipment_id);
        $db->execute();
        
        $success = 'Equipment status updated successfully.';
    }
}

// Get dashboard statistics
$db->query("SELECT COUNT(*) as count FROM assistance_tickets WHERE status = 'pending'");
$pending_tickets = $db->single()['count'];

$db->query("SELECT COUNT(*) as count FROM borrowing_requests WHERE status = 'pending'");
$pending_borrowings = $db->single()['count'];

$db->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'available'");
$available_equipment = $db->single()['count'];

$db->query("SELECT COUNT(*) as count FROM borrowing_requests WHERE status = 'overdue'");
$overdue_items = $db->single()['count'];

// Get recent tickets
$db->query("
    SELECT at.*, aty.name as assistance_type, s.first_name, s.last_name, s.email 
    FROM assistance_tickets at 
    LEFT JOIN assistance_types aty ON at.assistance_type_id = aty.id 
    LEFT JOIN students s ON at.student_id = s.student_id 
    ORDER BY at.created_at DESC 
    LIMIT 10
");
$recent_tickets = $db->resultSet();

// Get recent borrowing requests
$db->query("
    SELECT br.*, e.name as equipment_name, e.model, s.first_name, s.last_name, s.email 
    FROM borrowing_requests br 
    LEFT JOIN equipment e ON br.equipment_id = e.id 
    LEFT JOIN students s ON br.student_id = s.student_id 
    ORDER BY br.created_at DESC 
    LIMIT 10
");
$recent_borrowings = $db->resultSet();

// Get equipment inventory
$db->query("
    SELECT e.*, ec.name as category_name 
    FROM equipment e 
    LEFT JOIN equipment_categories ec ON e.category_id = ec.id 
    ORDER BY ec.name, e.name
");
$equipment_inventory = $db->resultSet();

// Get categories for add equipment form
$db->query("SELECT * FROM equipment_categories ORDER BY name");
$categories = $db->resultSet();

// Get admins for assignment
$db->query("SELECT id, full_name FROM admins ORDER BY full_name");
$admins = $db->resultSet();

// Helper functions
function getStatusBadge($status) {
    switch($status) {
        case 'pending': return 'bg-warning text-dark';
        case 'assigned': case 'approved': return 'bg-info';
        case 'in_progress': case 'active': return 'bg-primary';
        case 'resolved': case 'returned': return 'bg-success';
        case 'cancelled': case 'rejected': return 'bg-secondary';
        case 'overdue': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getPriorityBadge($priority) {
    switch($priority) {
        case 'low': return 'bg-secondary';
        case 'medium': return 'bg-primary';
        case 'high': return 'bg-warning text-dark';
        case 'urgent': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

// Automatically change overdue status for borrowings
$db->query("UPDATE borrowing_requests SET status = 'overdue' WHERE status = 'active' AND due_date < NOW()");


//Equipment usage statistics
$db->query("
    SELECT e.id, e.name, e.model, SUM(br.quantity_requested) as total_borrowed 
    FROM equipment e 
    LEFT JOIN borrowing_requests br ON e.id = br.equipment_id AND br.status IN ('active', 'returned') 
    GROUP BY e.id
");
$equipment_usage = $db->resultSet();

// Assistance type statistics
$db->query("
    SELECT aty.name as assistance_type, COUNT(at.id) as total_tickets 
    FROM assistance_tickets at 
    LEFT JOIN assistance_types aty ON at.assistance_type_id = aty.id 
    GROUP BY aty.name
");
$assistance_type_stats = $db->resultSet();
// Handle admin profile update


// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hardware Lab Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <button class="btn btn-link text-white d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="#">
                <i class="fas fa-tools me-2"></i>Admin Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($admin['name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="login.php"><i class="fas fa-home me-2"></i>Student Portal</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?logout=1"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="feedback.php"><i class="fas fa-mail me-2"></i>Feedback</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a class="nav-link active" href="#overview" data-section="overview">
                    <i class="fas fa-chart-pie me-2"></i>Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#tickets" data-section="tickets">
                    <i class="fas fa-ticket-alt me-2"></i>Assistance Tickets
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#borrowings" data-section="borrowings">
                    <i class="fas fa-handshake me-2"></i>Borrowing Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#equipment" data-section="equipment">
                    <i class="fas fa-network-wired me-2"></i>Equipment Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#profile" data-section="profile">
                    <i class="fas fa-users-cog me-2"></i>Admin Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#reports" data-section="reports">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Messages -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Overview Section -->
        <div id="overview-section" class="content-section">
            <h2 class="text-primary-custom mb-4">
                <i class="fas fa-chart-pie me-2"></i>Dashboard Overview
            </h2>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-clock text-warning" style="font-size: 2rem;"></i>
                            <h3 class="text-primary-custom mt-2"><?php echo $pending_tickets; ?></h3>
                            <p class="text-muted mb-0">Pending Tickets</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-hourglass-half text-info" style="font-size: 2rem;"></i>
                            <h3 class="text-primary-custom mt-2"><?php echo $pending_borrowings; ?></h3>
                            <p class="text-muted mb-0">Pending Borrowings</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                            <h3 class="text-primary-custom mt-2"><?php echo $available_equipment; ?></h3>
                            <p class="text-muted mb-0">Available Equipment</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                            <h3 class="text-primary-custom mt-2"><?php echo $overdue_items; ?></h3>
                            <p class="text-muted mb-0">Overdue Items</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-yellow-light">
                            <h5 class="mb-0 text-primary-custom">Recent Assistance Tickets</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_tickets)): ?>
                            <p class="text-muted text-center">No recent tickets</p>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($recent_tickets, 0, 5) as $ticket): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></small>
                                    </div>
                                    <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                        <?php echo ucfirst($ticket['status']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-primary btn-sm" onclick="showSection('tickets')">View All Tickets</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-yellow-light">
                            <h5 class="mb-0 text-primary-custom">Recent Borrowing Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_borrowings)): ?>
                            <p class="text-muted text-center">No recent borrowing requests</p>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($recent_borrowings, 0, 5) as $request): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['request_number']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($request['equipment_name']); ?></small>
                                    </div>
                                    <span class="badge <?php echo getStatusBadge($request['status']); ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-primary btn-sm" onclick="showSection('borrowings')">View All Requests</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Profile Section -->
            <div id="profile-section" class="content-section" style="display: none;">
                <h2 class="text-primary-custom mb-4">
                    <i class="fas fa-user-cog me-2"></i>Admin Profile & Management
                </h2>

                <div class="row">
                    <!-- Profile Update -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-user-edit me-2"></i>Update Profile
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary" name="update_profile">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-lock me-2"></i>Change Password
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning" name="change_password">
                                        <i class="fas fa-lock me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add New Admin -->
                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card shadow-sm mt-4">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-user-plus me-2"></i>Add New Admin
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <select class="form-select" name="role" required>
                                            <option value="admin">Admin</option>
                                            <option value="super_admin">Super Admin</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success" name="add_admin">
                                        <i class="fas fa-user-plus me-2"></i>Add Admin
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Tickets Section -->
        <div id="tickets-section" class="content-section" style="display: none;">
            <h2 class="text-primary-custom mb-4">
                <i class="fas fa-ticket-alt me-2"></i>Assistance Tickets Management
            </h2>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tickets as $ticket): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['assistance_type'] ?? 'General'); ?></td>
                                    <td>
                                        <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                            <?php echo ucfirst($ticket['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#ticketModal<?php echo $ticket['id']; ?>">
                                            <i class="fas fa-edit"></i> Manage
                                        </button>
                                    </td>
                                </tr>

                                <!-- Ticket Management Modal -->
                                <div class="modal fade" id="ticketModal<?php echo $ticket['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Manage Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Student:</strong> <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?><br>
                                                            <strong>Email:</strong> <?php echo htmlspecialchars($ticket['email']); ?><br>
                                                            <strong>Type:</strong> <?php echo htmlspecialchars($ticket['assistance_type'] ?? 'General'); ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Priority:</strong> 
                                                            <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                                                <?php echo ucfirst($ticket['priority']); ?>
                                                            </span><br>
                                                            <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Title:</strong><br>
                                                        <?php echo htmlspecialchars($ticket['title']); ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Description:</strong><br>
                                                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                                                    </div>
                                                    
                                                    <hr>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status" required>
                                                                    <option value="pending" <?php echo $ticket['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="assigned" <?php echo $ticket['status'] === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                                                    <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                    <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                    <option value="cancelled" <?php echo $ticket['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Assign To</label>
                                                                <select class="form-select" name="assigned_to">
                                                                    <option value="">Unassigned</option>
                                                                    <?php foreach ($admins as $admin_option): ?>
                                                                    <option value="<?php echo $admin_option['id']; ?>" 
                                                                            <?php echo $ticket['assigned_to'] == $admin_option['id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($admin_option['full_name']); ?>
                                                                    </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Scheduled Date</label>
                                                                <input type="date" class="form-control" name="scheduled_date" 
                                                                       value="<?php echo $ticket['scheduled_date']; ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Scheduled Time</label>
                                                                <input type="time" class="form-control" name="scheduled_time" 
                                                                       value="<?php echo $ticket['scheduled_time']; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Add Comment</label>
                                                        <textarea class="form-control" name="comment" rows="3" 
                                                                  placeholder="Add a comment about this ticket..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="update_ticket_status" class="btn btn-primary">Update Ticket</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Borrowings Section -->
        <div id="borrowings-section" class="content-section" style="display: none;">
            <h2 class="text-primary-custom mb-4">
                <i class="fas fa-handshake me-2"></i>Borrowing Requests Management
            </h2>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request #</th>
                                    <th>Student</th>
                                    <th>Equipment</th>
                                    <th>Status</th>
                                    <th>Requested On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_borrowings as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['request_number']); ?></td>
                                    <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['equipment_name'] . ' (' . $request['model'] . ')'); ?></td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadge($request['status']); ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#borrowingModal<?php echo $request['id']; ?>">
                                            <i class="fas fa-edit"></i> Manage
                                        </button>
                                    </td>
                                </tr>

                                <!-- Borrowing Management Modal -->
                                <div class="modal fade" id="borrowingModal<?php echo $request['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Manage Request #<?php echo htmlspecialchars($request['request_number']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Student:</strong> <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?><br>
                                                            <strong>Email:</strong> <?php echo htmlspecialchars($request['email']); ?><br>
                                                            <strong>Equipment:</strong> <?php echo htmlspecialchars($request['equipment_name'] . ' ' . $request['model']); ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Quantity:</strong> <?php echo $request['quantity_requested']; ?><br>
                                                            <strong>Requested:</strong> <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?><br>
                                                            <strong>Purpose:</strong> <?php echo htmlspecialchars($request['purpose'] ?? 'N/A'); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Current Notes:</strong><br>
                                                        <?php echo nl2br(htmlspecialchars($request['notes'] ?? 'No notes')); ?>
                                                    </div>
                                                    
                                                    <hr>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status" required>
                                                                    <option value="pending" <?php echo $request['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="approved" <?php echo $request['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                                    <option value="active" <?php echo $request['status'] === 'active' ? 'selected' : ''; ?>>Active (Borrowed)</option>
                                                                    <option value="returned" <?php echo $request['status'] === 'returned' ? 'selected' : ''; ?>>Returned</option>
                                                                    <option value="rejected" <?php echo $request['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                                    <option value="overdue" <?php echo $request['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Due Date (for active status)</label>
                                                                <input type="date" class="form-control" name="due_date" 
                                                                       value="<?php echo $request['due_date']; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Admin Notes</label>
                                                        <textarea class="form-control" name="notes" rows="3" 
                                                                  placeholder="Add notes about this borrowing request..."><?php echo htmlspecialchars($request['notes'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="update_borrowing_status" class="btn btn-primary">Update Request</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Equipment Section -->
        <div id="equipment-section" class="content-section" style="display: none;">
            <h2 class="text-primary-custom mb-4">
                <i class="fas fa-network-wired me-2"></i>Equipment Management
            </h2>

            <!-- Add Equipment Button -->
            <div class="mb-4">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                    <i class="fas fa-plus me-2"></i>Add New Equipment
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Model</th>
                                    <th>Serial Number</th>
                                    <th>Total Qty</th>
                                    <th>Available</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment_inventory as $equipment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($equipment['name']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['model']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['serial_number']); ?></td>
                                    <td><?php echo $equipment['total_quantity']; ?></td>
                                    <td><?php echo $equipment['quantity_available']; ?></td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadge($equipment['status']); ?>">
                                            <?php echo ucfirst($equipment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#equipmentModal<?php echo $equipment['id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>

                                <!-- Equipment Management Modal -->
                                <div class="modal fade" id="equipmentModal<?php echo $equipment['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Equipment: <?php echo htmlspecialchars($equipment['name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="equipment_id" value="<?php echo $equipment['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Equipment Status</label>
                                                        <select class="form-select" name="equipment_status" required>
                                                            <option value="available" <?php echo $equipment['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                                            <option value="maintenance" <?php echo $equipment['status'] === 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                                            <option value="damaged" <?php echo $equipment['status'] === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                                                            <option value="retired" <?php echo $equipment['status'] === 'retired' ? 'selected' : ''; ?>>Retired</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Total Quantity</label>
                                                                <input type="number" class="form-control" value="<?php echo $equipment['total_quantity']; ?>" disabled>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Available Quantity</label>
                                                                <input type="number" class="form-control" value="<?php echo $equipment['quantity_available']; ?>" disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="alert alert-info">
                                                        <small><i class="fas fa-info-circle me-2"></i>Quantity changes are managed automatically through borrowing transactions.</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="update_equipment_status" class="btn btn-primary">Update Status</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Equipment Modal -->
            <div class="modal fade" id="addEquipmentModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Equipment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Equipment Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Model</label>
                                    <input type="text" class="form-control" name="model" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" name="serial_number">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Total Quantity</label>
                                    <input type="number" class="form-control" name="total_quantity" min="1" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" name="add_equipment" class="btn btn-primary">Add Equipment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Section -->
           <div id="reports-section" class="content-section" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-yellow-light">
                                <h5 class="mb-0 text-primary-custom">Equipment Usage Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($equipment_usage)): ?>
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Equipment</th>
                                            <th>Model</th>
                                            <th>Total Borrowed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($equipment_usage as $usage): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($usage['name']); ?></td>
                                            <td><?php echo htmlspecialchars($usage['model']); ?></td>
                                            <td><?php echo (int)$usage['total_borrowed']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <p class="text-muted">No equipment usage data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-yellow-light">
                                <h5 class="mb-0 text-primary-custom">Assistance Tickets Analytics</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($assistance_type_stats)): ?>
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Assistance Type</th>
                                            <th>Total Tickets</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assistance_type_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['assistance_type']); ?></td>
                                            <td><?php echo (int)$stat['total_tickets']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <p class="text-muted">No assistance ticket data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Navigation functionality
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Remove active class from all nav links
            document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').style.display = 'block';
            
            // Add active class to clicked nav link
            document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');
        }

        // Handle sidebar navigation clicks
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const section = this.getAttribute('data-section');
                showSection(section);
            });
        });

        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            if (document.getElementById('overview-section').style.display !== 'none') {
                location.reload();
            }
        }, 30000);

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>