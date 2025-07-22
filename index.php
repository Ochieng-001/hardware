<?php
require_once 'config.php';

$error = '';
$success = '';
$student = null;

// Handle student verification
if ($_POST && isset($_POST['verify_student'])) {
    $email = sanitizeInput($_POST['email']);
    $student_id = sanitizeInput($_POST['student_id']);
    
    // Verify student exists
    $db->query("SELECT * FROM students WHERE email = :email AND student_id = :student_id");
    $db->bind(':email', $email);
    $db->bind(':student_id', $student_id);
    
    $student = $db->single();
    
    if (!$student) {
        $error = 'Invalid email or student ID. Please check your credentials.';
    } else {
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['student_email'] = $student['email'];
        $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
    }
}

// Check if student is already verified
if (isset($_SESSION['student_id']) && !$student) {
    $db->query("SELECT * FROM students WHERE student_id = :student_id");
    $db->bind(':student_id', $_SESSION['student_id']);
    $student = $db->single();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
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
    <link rel ="stylesheet" href="index.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tools me-2"></i>Hardware Lab System
            </a>
            <?php if ($student): ?>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    Welcome, <?php echo htmlspecialchars($student['first_name']); ?>!
                </span>
                <a href="?logout=1" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <?php if (!$student): ?>
    <!-- Student Verification Form -->
    <div class="hero-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <i class="fas fa-user-check icon-large"></i>
                                <h2 class="text-primary-custom">Student Verification</h2>
                                <p class="text-muted">Enter your school email and student ID to access lab services</p>
                            </div>

                            <?php if ($error): ?>
                            <div class="alert alert-danger alert-custom">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">School Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" required 
                                               placeholder="youremail@university.edu">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Student ID</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" class="form-control" name="student_id" required 
                                               placeholder="Your school ID">
                                    </div>
                                </div>
                                
                                <button type="submit" name="verify_student" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Access Lab Services
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Student Dashboard -->
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-custom bg-yellow-light">
                    <h4 class="alert-heading text-primary-custom">
                        <i class="fas fa-hand-wave me-2"></i>Welcome to USIU Hardware Lab!
                    </h4>
                    <p class="mb-0">Choose from the options below to get technical assistance or borrow equipment.</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Technical Assistance -->
            <div class="col-md-6">
                <div class="card service-card h-100">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-tools icon-large"></i>
                        <h3 class="text-primary-custom">Technical Assistance</h3>
                        <p class="text-muted mb-4">Get help with software installation, PC troubleshooting, and other technical issues.</p>
                        <ul class="list-unstyled text-start mb-4">
                            <li><i class="fas fa-check-circle text-success me-2"></i>Microsoft Office Setup</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>SPSS Installation</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>PC Troubleshooting</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Network Configuration</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Software Installation</li>
                        </ul>
                        <a href="assistance.php" class="btn btn-primary">
                            <i class="fas fa-ticket-alt me-2"></i>Request Assistance
                        </a>
                    </div>
                </div>
            </div>

            <!-- Equipment Borrowing -->
            <div class="col-md-6">
                <div class="card service-card h-100">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-network-wired icon-large"></i>
                        <h3 class="text-primary-custom">Equipment Borrowing</h3>
                        <p class="text-muted mb-4">Borrow networking equipment and tools for your projects and assignments.</p>
                        <ul class="list-unstyled text-start mb-4">
                            <li><i class="fas fa-check-circle text-success me-2"></i>Network Switches</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Routers</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Ethernet Cables</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Network Testing Tools</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i>Various Cables & Adapters</li>
                        </ul>
                        <a href="borrowing.php" class="btn btn-warning">
                            <i class="fas fa-handshake me-2"></i>Borrow Equipment
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Status Overview -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="text-primary-custom">
                            <i class="fas fa-chart-line me-2"></i>Your Activity Overview
                        </h4>
                        <div class="row text-center mt-4">
                            <?php
                            // Get student statistics
                            $db->query("SELECT COUNT(*) as count FROM assistance_tickets WHERE student_id = :student_id");
                            $db->bind(':student_id', $student['student_id']);
                            $assistance_count = $db->single()['count'];

                            $db->query("SELECT COUNT(*) as count FROM borrowing_requests WHERE student_id = :student_id");
                            $db->bind(':student_id', $student['student_id']);
                            $borrowing_count = $db->single()['count'];

                            $db->query("SELECT COUNT(*) as count FROM assistance_tickets WHERE student_id = :student_id AND status = 'resolved'");
                            $db->bind(':student_id', $student['student_id']);
                            $resolved_count = $db->single()['count'];
                            ?>
                            
                            <div class="col-md-4">
                                <div class="bg-yellow-light p-3 rounded">
                                    <h3 class="text-primary-custom"><?php echo $assistance_count; ?></h3>
                                    <p class="mb-0">Assistance Requests</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-yellow-light p-3 rounded">
                                    <h3 class="text-primary-custom"><?php echo $borrowing_count; ?></h3>
                                    <p class="mb-0">Borrowing Requests</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-yellow-light p-3 rounded">
                                    <h3 class="text-primary-custom"><?php echo $resolved_count; ?></h3>
                                    <p class="mb-0">Issues Resolved</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="my-requests.php" class="btn btn-primary">
                                <i class="fas fa-history me-2"></i>View My Requests
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p class="text-muted mb-0">
                <i class="fas fa-tools me-2"></i>Hardware Lab Management System | 
                <a href="login.php" class="text-decoration-none">Login</a>
            </p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>