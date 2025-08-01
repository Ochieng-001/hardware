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
$student_info = null;

// Get assistance types for dropdown
try {
    $db->query("SELECT * FROM assistance_types ORDER BY name");
    $assistance_types = $db->resultSet();
} catch (Exception $e) {
    $assistance_types = [];
    error_log("Error loading assistance types: " . $e->getMessage());
}

// Get equipment categories and items for borrowing requests
try {
    $db->query("SELECT * FROM equipment_categories ORDER BY name");
    $categories = $db->resultSet();

    $db->query("
        SELECT e.*, ec.name as category_name 
        FROM equipment e 
        LEFT JOIN equipment_categories ec ON e.category_id = ec.id 
        WHERE e.status = 'available' AND e.quantity_available > 0
        ORDER BY ec.name, e.name
    ");
    $available_equipment = $db->resultSet();
} catch (Exception $e) {
    $categories = [];
    $available_equipment = [];
    error_log("Error loading equipment: " . $e->getMessage());
}

// Handle student lookup
if (isset($_POST['lookup_student'])) {
    $student_search = isset($_POST['student_search']) ? trim($_POST['student_search']) : '';
    
    if (!empty($student_search)) {
        try {
            // Search by student ID or email (case insensitive) - FIXED PARAMETER BINDING
            $db->query("SELECT * FROM students WHERE LOWER(student_id) = LOWER(:search1) OR LOWER(email) = LOWER(:search2)");
            $db->bind(':search1', $student_search);
            $db->bind(':search2', $student_search);
            $student_info = $db->single();
            
            if (!$student_info) {
                $error = 'Student not found. Please check the Student ID or email address.';
                
                // Also try a broader search
                $db->query("SELECT * FROM students WHERE student_id LIKE :search1 OR email LIKE :search2 OR CONCAT(first_name, ' ', last_name) LIKE :search3");
                $db->bind(':search1', '%' . $student_search . '%');
                $db->bind(':search2', '%' . $student_search . '%');
                $db->bind(':search3', '%' . $student_search . '%');
                $similar_students = $db->resultSet();
                
                if (!empty($similar_students)) {
                    $error .= ' Did you mean: ';
                    $suggestions = [];
                    foreach (array_slice($similar_students, 0, 3) as $student) {
                        $suggestions[] = $student['student_id'] . ' (' . $student['first_name'] . ' ' . $student['last_name'] . ')';
                    }
                    $error .= implode(', ', $suggestions);
                }
            } else {
                $success = 'Student found: ' . htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']);
            }
        } catch (Exception $e) {
            $error = 'Database error occurred while searching. Please try again.';
            error_log("Database error in student search: " . $e->getMessage());
        }
    } else {
        $error = 'Please enter a Student ID or email address.';
    }
}

// Handle assistance ticket creation
if (isset($_POST['create_assistance_ticket'])) {
    $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    $assistance_type_id = isset($_POST['assistance_type_id']) ? $_POST['assistance_type_id'] : null;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
    $phone_request = isset($_POST['phone_request']) ? 1 : 0;
    $caller_notes = isset($_POST['caller_notes']) ? trim($_POST['caller_notes']) : '';
    
    if (empty($student_id) || empty($title) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Verify student exists
            $db->query("SELECT * FROM students WHERE student_id = :student_id");
            $db->bind(':student_id', $student_id);
            $student = $db->single();
            
            if (!$student) {
                $error = 'Invalid student ID.';
            } else {
                // Generate ticket number
                $ticket_number = 'TK' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Insert assistance ticket
                $db->query("
                    INSERT INTO assistance_tickets 
                    (ticket_number, student_id, assistance_type_id, title, description, priority, status, assigned_to, phone_request, caller_notes) 
                    VALUES 
                    (:ticket_number, :student_id, :assistance_type_id, :title, :description, :priority, 'pending', :assigned_to, :phone_request, :caller_notes)
                ");
                $db->bind(':ticket_number', $ticket_number);
                $db->bind(':student_id', $student_id);
                $db->bind(':assistance_type_id', $assistance_type_id);
                $db->bind(':title', $title);
                $db->bind(':description', $description);
                $db->bind(':priority', $priority);
                $db->bind(':assigned_to', $admin['id']);
                $db->bind(':phone_request', $phone_request);
                $db->bind(':caller_notes', $caller_notes);
                $db->execute();
                
                $success = "Assistance ticket #{$ticket_number} created successfully for {$student['first_name']} {$student['last_name']}.";
                
                // Keep student_info so form doesn't clear after successful creation
                $db->query("SELECT * FROM students WHERE student_id = :student_id");
                $db->bind(':student_id', $student_id);
                $student_info = $db->single();
            }
        } catch (Exception $e) {
            $error = 'Error creating assistance ticket: ' . $e->getMessage();
            error_log("Error creating assistance ticket: " . $e->getMessage());
        }
    }
}

// Handle borrowing request creation
if (isset($_POST['create_borrowing_request'])) {
    $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    $equipment_id = isset($_POST['equipment_id']) ? $_POST['equipment_id'] : null;
    $quantity_requested = isset($_POST['quantity_requested']) ? (int)$_POST['quantity_requested'] : 0;
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $requested_from = isset($_POST['requested_from']) ? $_POST['requested_from'] : '';
    $requested_to = isset($_POST['requested_to']) ? $_POST['requested_to'] : '';
    $phone_request = isset($_POST['phone_request']) ? 1 : 0;
    $caller_notes = isset($_POST['caller_notes']) ? trim($_POST['caller_notes']) : '';
    
    if (empty($student_id) || empty($equipment_id) || empty($purpose) || empty($requested_from) || empty($requested_to)) {
        $error = 'Please fill in all required fields.';
    } elseif ($quantity_requested < 1) {
        $error = 'Quantity must be at least 1.';
    } elseif (strtotime($requested_from) > strtotime($requested_to)) {
        $error = 'Return date must be after the borrowing date.';
    } else {
        try {
            // Verify student exists
            $db->query("SELECT * FROM students WHERE student_id = :student_id");
            $db->bind(':student_id', $student_id);
            $student = $db->single();
            
            if (!$student) {
                $error = 'Invalid student ID.';
            } else {
                // Check equipment availability
                $db->query("SELECT * FROM equipment WHERE id = :equipment_id AND quantity_available >= :quantity");
                $db->bind(':equipment_id', $equipment_id);
                $db->bind(':quantity', $quantity_requested);
                $equipment = $db->single();
                
                if (!$equipment) {
                    $error = 'Equipment not available or insufficient quantity.';
                } else {
                    // Generate request number
                    $request_number = 'BR' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Insert borrowing request
                    $db->query("
                        INSERT INTO borrowing_requests 
                        (request_number, student_id, equipment_id, quantity_requested, purpose, requested_from, requested_to, status, phone_request, caller_notes) 
                        VALUES 
                        (:request_number, :student_id, :equipment_id, :quantity_requested, :purpose, :requested_from, :requested_to, 'pending', :phone_request, :caller_notes)
                    ");
                    $db->bind(':request_number', $request_number);
                    $db->bind(':student_id', $student_id);
                    $db->bind(':equipment_id', $equipment_id);
                    $db->bind(':quantity_requested', $quantity_requested);
                    $db->bind(':purpose', $purpose);
                    $db->bind(':requested_from', $requested_from);
                    $db->bind(':requested_to', $requested_to);
                    $db->bind(':phone_request', $phone_request);
                    $db->bind(':caller_notes', $caller_notes);
                    $db->execute();
                    
                    $success = "Borrowing request #{$request_number} created successfully for {$student['first_name']} {$student['last_name']}.";
                    
                    // Keep student_info so form doesn't clear after successful creation
                    $db->query("SELECT * FROM students WHERE student_id = :student_id");
                    $db->bind(':student_id', $student_id);
                    $student_info = $db->single();
                }
            }
        } catch (Exception $e) {
            $error = 'Error creating borrowing request: ' . $e->getMessage();
            error_log("Error creating borrowing request: " . $e->getMessage());
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
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i>Add Student Request
            </a>
            <div class="navbar-nav ms-auto">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($admin['name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-dashboard me-2"></i>Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-5" style="margin-top: 3rem;">
        <!-- Messages -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Student Lookup Section -->
        <div class="row mb-4">
            <div class="col-lg-6 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Student Lookup</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="input-group">
                                <input type="text" class="form-control" name="student_search" 
                                       placeholder="Enter Student ID or Email Address" 
                                       value="<?php echo isset($_POST['student_search']) ? htmlspecialchars($_POST['student_search']) : ''; ?>" required>
                                <button class="btn btn-primary" type="submit" name="lookup_student">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                            <small class="form-text text-muted">Search by Student ID (e.g., ST001) or email address</small>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Information Display -->
        <?php if ($student_info && !$error): ?>
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm student-info-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Student Found</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_info['student_id']); ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($student_info['email']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Course:</strong> <?php echo htmlspecialchars($student_info['course'] ?? 'N/A'); ?></p>
                                <p><strong>Year of Study:</strong> <?php echo htmlspecialchars($student_info['year_of_study'] ?? 'N/A'); ?></p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Student verified. You can now create requests for this student.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Type Tabs -->
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow-sm request-form-card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="requestTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="assistance-tab" data-bs-toggle="tab" data-bs-target="#assistance" type="button" role="tab">
                                    <i class="fas fa-hands-helping me-2"></i>Assistance Request
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="borrowing-tab" data-bs-toggle="tab" data-bs-target="#borrowing" type="button" role="tab">
                                    <i class="fas fa-handshake me-2"></i>Equipment Borrowing
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="requestTabsContent">
                            <!-- Assistance Request Tab -->
                            <div class="tab-pane fade show active" id="assistance" role="tabpanel">
                                <div class="phone-request-indicator">
                                    <i class="fas fa-phone me-2"></i>
                                    <strong>Phone Request:</strong> Creating request for student who called the help desk
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_info['student_id']); ?>">
                                    
                                    <div class="form-group-enhanced">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Assistance Type</label>
                                                <select class="form-select" name="assistance_type_id">
                                                    <option value="">General Assistance</option>
                                                    <?php foreach ($assistance_types as $type): ?>
                                                    <option value="<?php echo $type['id']; ?>">
                                                        <?php echo htmlspecialchars($type['name']); ?>
                                                        <?php if ($type['estimated_duration']): ?>
                                                            (<?php echo $type['estimated_duration']; ?> min)
                                                        <?php endif; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Priority <span class="required-field">*</span></label>
                                                <select class="form-select" name="priority" required>
                                                    <option value="medium" selected>Medium</option>
                                                    <option value="low">Low</option>
                                                    <option value="high">High</option>
                                                    <option value="urgent">Urgent</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Title <span class="required-field">*</span></label>
                                        <input type="text" class="form-control" name="title" required 
                                               placeholder="Brief description of the issue">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description <span class="required-field">*</span></label>
                                        <textarea class="form-control" name="description" rows="4" required
                                                  placeholder="Detailed description of the problem or assistance needed&#10;&#10;Include:&#10;- What the student was trying to do&#10;- Error messages (if any)&#10;- When the problem started&#10;- Steps already tried"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="phone_request" id="phone_request_assistance" checked>
                                            <label class="form-check-label" for="phone_request_assistance">
                                                <strong>This is a phone request</strong> (student called for help)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Admin Notes</label>
                                        <textarea class="form-control" name="caller_notes" rows="3"
                                                  placeholder="Additional notes about the phone call:&#10;- Student's callback number&#10;- Urgency details&#10;- Any special circumstances&#10;- Follow-up requirements"></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="button" class="btn btn-secondary me-md-2" onclick="clearForm('assistance')">
                                            <i class="fas fa-eraser me-2"></i>Clear Form
                                        </button>
                                        <button type="submit" name="create_assistance_ticket" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Create Assistance Ticket
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Equipment Borrowing Tab -->
                            <div class="tab-pane fade" id="borrowing" role="tabpanel">
                                <div class="phone-request-indicator">
                                    <i class="fas fa-phone me-2"></i>
                                    <strong>Phone Request:</strong> Creating borrowing request for student who called
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_info['student_id']); ?>">
                                    
                                    <div class="form-group-enhanced">
                                        <div class="row mb-3">
                                            <div class="col-md-8">
                                                <label class="form-label">Equipment <span class="required-field">*</span></label>
                                                <select class="form-select" name="equipment_id" required onchange="updateAvailableQuantity(this)">
                                                    <option value="">Select Equipment</option>
                                                    <?php 
                                                    $current_category = '';
                                                    foreach ($available_equipment as $equipment): 
                                                        if ($current_category != $equipment['category_name']):
                                                            if ($current_category != '') echo '</optgroup>';
                                                            echo '<optgroup label="' . htmlspecialchars($equipment['category_name']) . '">';
                                                            $current_category = $equipment['category_name'];
                                                        endif;
                                                    ?>
                                                    <option value="<?php echo $equipment['id']; ?>" 
                                                            data-available="<?php echo $equipment['quantity_available']; ?>">
                                                        <?php echo htmlspecialchars($equipment['name'] . ' - ' . $equipment['model']); ?>
                                                        (Available: <?php echo $equipment['quantity_available']; ?>)
                                                    </option>
                                                    <?php 
                                                    endforeach; 
                                                    if ($current_category != '') echo '</optgroup>';
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Quantity <span class="required-field">*</span></label>
                                                <input type="number" class="form-control" name="quantity_requested" 
                                                       min="1" max="1" value="1" required id="quantity_input">
                                                <small class="form-text text-muted">Max available: <span id="max_available">1</span></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Borrow From <span class="required-field">*</span></label>
                                            <input type="date" class="form-control" name="requested_from" 
                                                   min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Return By <span class="required-field">*</span></label>
                                            <input type="date" class="form-control" name="requested_to" 
                                                   min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Purpose <span class="required-field">*</span></label>
                                        <textarea class="form-control" name="purpose" rows="3" required
                                                  placeholder="Describe the purpose for borrowing this equipment:&#10;- Course/project name&#10;- Specific use case&#10;- Learning objectives"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="phone_request" id="phone_request_borrowing" checked>
                                            <label class="form-check-label" for="phone_request_borrowing">
                                                <strong>This is a phone request</strong> (student called for help)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Admin Notes</label>
                                        <textarea class="form-control" name="caller_notes" rows="3"
                                                  placeholder="Additional notes about the phone call:&#10;- Student's callback number&#10;- Special handling instructions&#10;- Pickup arrangements&#10;- Any concerns or requirements"></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="button" class="btn btn-secondary me-md-2" onclick="clearForm('borrowing')">
                                            <i class="fas fa-eraser me-2"></i>Clear Form
                                        </button>
                                        <button type="submit" name="create_borrowing_request" class="btn btn-success">
                                            <i class="fas fa-plus me-2"></i>Create Borrowing Request
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Instructions when no student is selected -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-search text-muted" style="font-size: 3rem; opacity: 0.5;"></i>
                        <h5 class="text-muted mt-3">Search for a Student</h5>
                        <p class="text-muted mb-0">Enter a Student ID or email address above to find the student and create requests on their behalf.</p>
                        
                        <div class="mt-4">
                            <h6 class="text-muted">Student ID Format:</h6>
                            <div class="d-flex justify-content-center flex-wrap gap-2 mt-2">
                                <span class="badge bg-secondary">ST001</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Clear form function
        function clearForm(formType) {
            const form = document.querySelector(`#${formType} form`);
            if (form && confirm('Clear the current form?')) {
                form.reset();
            }
        }

        // Update available quantity when equipment is selected
        function updateAvailableQuantity(select) {
            const selectedOption = select.options[select.selectedIndex];
            const available = selectedOption.getAttribute('data-available') || 1;
            const quantityInput = document.getElementById('quantity_input');
            const maxAvailableSpan = document.getElementById('max_available');
            
            quantityInput.max = available;
            quantityInput.value = Math.min(quantityInput.value, available);
            maxAvailableSpan.textContent = available;
            
            // Update visual indicator
            if (available > 0) {
                quantityInput.classList.remove('is-invalid');
                quantityInput.classList.add('is-valid');
            } else {
                quantityInput.classList.remove('is-valid');
                quantityInput.classList.add('is-invalid');
            }
        }
        
        // Set minimum dates for borrowing
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('input[type="date"]').forEach(input => {
                if (input.getAttribute('min') === '<?php echo date('Y-m-d'); ?>') {
                    input.setAttribute('min', today);
                }
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                if (bootstrap.Alert.getOrCreateInstance) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);
    </script>
</body>
</html>