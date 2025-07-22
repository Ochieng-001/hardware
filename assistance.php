<?php
require_once 'config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Get student info
$db->query("SELECT * FROM students WHERE student_id = :student_id");
$db->bind(':student_id', $_SESSION['student_id']);
$student = $db->single();

// Get assistance types
$db->query("SELECT * FROM assistance_types ORDER BY name ASC");
$assistance_types = $db->resultSet();

// Handle form submission
if ($_POST && isset($_POST['submit_request'])) {
    $assistance_type_id = sanitizeInput($_POST['assistance_type_id']);
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $priority = sanitizeInput($_POST['priority']);
    
    // Validation
    if (empty($assistance_type_id) || empty($title) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Generate ticket number
            $ticket_number = generateTicketNumber();
            
            // Insert assistance ticket
            $db->query("INSERT INTO assistance_tickets (ticket_number, student_id, assistance_type_id, title, description, priority) 
                       VALUES (:ticket_number, :student_id, :assistance_type_id, :title, :description, :priority)");
            $db->bind(':ticket_number', $ticket_number);
            $db->bind(':student_id', $student['student_id']);
            $db->bind(':assistance_type_id', $assistance_type_id);
            $db->bind(':title', $title);
            $db->bind(':description', $description);
            $db->bind(':priority', $priority);
            
            if ($db->execute()) {
                $success = 'Your assistance request has been submitted successfully! Ticket Number: ' . $ticket_number;
                // Clear form data
                $_POST = array();
            } else {
                $error = 'Failed to submit your request. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred while processing your request.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Technical Assistance - Hardware Lab</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assistance.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tools me-2"></i>Hardware Lab System
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    Welcome, <?php echo htmlspecialchars($student['first_name']); ?>!
                </span>
                <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="?logout=1" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center">
                    <i class="fas fa-tools text-primary-custom" style="font-size: 3rem;"></i>
                    <h2 class="text-primary-custom mt-3">Request Technical Assistance</h2>
                    <p class="text-muted">Fill out the form below to get help from our technical support team</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-custom mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success alert-custom mb-4">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <div class="mt-2">
                        <a href="my-requests.php" class="btn btn-sm btn-primary">View My Requests</a>
                        <a href="index.php" class="btn btn-sm btn-outline-primary">Back to Home</a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-5">
                        <form method="POST" action="">
                            <!-- Assistance Type Selection -->
                            <div class="mb-4">
                                <label class="form-label h5 text-primary-custom">
                                    <i class="fas fa-list-alt me-2"></i>Type of Assistance Needed *
                                </label>
                                <div class="row">
                                    <?php foreach ($assistance_types as $type): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card assistance-type-card" onclick="selectAssistanceType(<?php echo $type['id']; ?>)">
                                            <div class="card-body p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="assistance_type_id" 
                                                           value="<?php echo $type['id']; ?>" id="type<?php echo $type['id']; ?>" 
                                                           <?php echo (isset($_POST['assistance_type_id']) && $_POST['assistance_type_id'] == $type['id']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="type<?php echo $type['id']; ?>">
                                                        <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($type['description']); ?></small>
                                                        <br><small class="text-info">Est. Time: <?php echo $type['estimated_duration']; ?> minutes</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Issue Title -->
                            <div class="mb-4">
                                <label class="form-label h5 text-primary-custom">
                                    <i class="fas fa-heading me-2"></i>Issue Title *
                                </label>
                                <input type="text" class="form-control" name="title" required
                                       placeholder="Brief description of your issue"
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>

                            <!-- Detailed Description -->
                            <div class="mb-4">
                                <label class="form-label h5 text-primary-custom">
                                    <i class="fas fa-align-left me-2"></i>Detailed Description *
                                </label>
                                <textarea class="form-control" name="description" rows="5" required
                                          placeholder="Please provide detailed information about your issue, including any error messages, steps you've tried, and specific problems you're experiencing..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-text">The more details you provide, the better we can assist you.</div>
                            </div>

                            <!-- Priority Level -->
                            <div class="mb-4">
                                <label class="form-label h5 text-primary-custom">
                                    <i class="fas fa-exclamation-circle me-2"></i>Priority Level
                                </label>
                                <select class="form-select" name="priority">
                                    <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>
                                        Low - Can wait a few days
                                    </option>
                                    <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>
                                        Medium - Needed within 24-48 hours
                                    </option>
                                    <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>
                                        High - Urgent, needed today
                                    </option>
                                    <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>
                                        Urgent - Critical issue, immediate attention needed
                                    </option>
                                </select>
                            </div>

                            <!-- Student Information Display -->
                            <div class="mb-4">
                                <div class="bg-yellow-light p-3 rounded">
                                    <h6 class="text-primary-custom mb-2">Your Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                    <p class="mb-1"><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                                    <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center">
                                <button type="submit" name="submit_request" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Request
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg ms-3">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Home
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAssistanceType(typeId) {
            // Remove selected class from all cards
            document.querySelectorAll('.assistance-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select the radio button
            document.getElementById('type' + typeId).checked = true;
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
        }
        
        // Add selected class to initially checked radio button
        document.addEventListener('DOMContentLoaded', function() {
            const checkedRadio = document.querySelector('input[name="assistance_type_id"]:checked');
            if (checkedRadio) {
                checkedRadio.closest('.assistance-type-card').classList.add('selected');
            }
        });
        
        // Handle logout
        const logoutLink = document.querySelector('a[href="?logout=1"]');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        }
    </script>
</body>
</html>