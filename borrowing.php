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

// Get available equipment with categories
$db->query("SELECT e.*, ec.name as category_name 
           FROM equipment e 
           INNER JOIN equipment_categories ec ON e.category_id = ec.id 
           WHERE e.quantity_available > 0 
           ORDER BY ec.name, e.name");
$equipment = $db->resultSet();

// Group equipment by category
$equipment_by_category = [];
foreach ($equipment as $item) {
    $equipment_by_category[$item['category_name']][] = $item;
}

// Handle form submission
if ($_POST && isset($_POST['submit_request'])) {
    $equipment_id = sanitizeInput($_POST['equipment_id']);
    $quantity = (int)sanitizeInput($_POST['quantity']);
    $purpose = sanitizeInput($_POST['purpose']);
    $requested_from = sanitizeInput($_POST['requested_from']);
    $requested_to = sanitizeInput($_POST['requested_to']);
    
    // Validation
    if (empty($equipment_id) || empty($purpose) || empty($requested_from) || empty($requested_to) || $quantity <= 0) {
        $error = 'Please fill in all required fields with valid values.';
    } elseif (strtotime($requested_from) >= strtotime($requested_to)) {
        $error = 'Return date must be after the borrowing date.';
    } elseif (strtotime($requested_from) < strtotime(date('Y-m-d'))) {
        $error = 'Borrowing date cannot be in the past.';
    } else {
        // Check if equipment exists and has enough quantity
        $db->query("SELECT * FROM equipment WHERE id = :equipment_id");
        $db->bind(':equipment_id', $equipment_id);
        $selected_equipment = $db->single();
        
        if (!$selected_equipment) {
            $error = 'Selected equipment not found.';
        } elseif ($quantity > $selected_equipment['quantity_available']) {
            $error = 'Requested quantity exceeds available stock. Available: ' . $selected_equipment['quantity_available'];
        } else {
            try {
                // Generate request number
                $request_number = generateRequestNumber();
                
                // Insert borrowing request
                $db->query("INSERT INTO borrowing_requests (request_number, student_id, equipment_id, quantity_requested, purpose, requested_from, requested_to) 
                           VALUES (:request_number, :student_id, :equipment_id, :quantity, :purpose, :requested_from, :requested_to)");
                $db->bind(':request_number', $request_number);
                $db->bind(':student_id', $student['student_id']);
                $db->bind(':equipment_id', $equipment_id);
                $db->bind(':quantity', $quantity);
                $db->bind(':purpose', $purpose);
                $db->bind(':requested_from', $requested_from);
                $db->bind(':requested_to', $requested_to);
                
                if ($db->execute()) {
                    $success = 'Your borrowing request has been submitted successfully! Request Number: ' . $request_number;
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
    <title>Borrow Equipment - Hardware Lab</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="borrowing.css">
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
                    <i class="fas fa-network-wired text-primary-custom" style="font-size: 3rem;"></i>
                    <h2 class="text-primary-custom mt-3">Borrow Equipment</h2>
                    <p class="text-muted">Select equipment you need for your projects and assignments</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Equipment Selection -->
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
                        <a href="requests.php" class="btn btn-sm btn-primary">View My Requests</a>
                        <a href="index.php" class="btn btn-sm btn-outline-primary">Back to Home</a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <h5 class="text-primary-custom mb-4">
                            <i class="fas fa-list me-2"></i>Available Equipment
                        </h5>

                        <form method="POST" action="" id="borrowingForm">
                            <?php foreach ($equipment_by_category as $category => $items): ?>
                            <div class="category-header p-2 rounded">
                                <h6 class="mb-0"><i class="fas fa-folder me-2"></i><?php echo htmlspecialchars($category); ?></h6>
                            </div>
                            
                            <div class="row mb-4">
                                <?php foreach ($items as $item): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card equipment-card position-relative" onclick="selectEquipment(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', <?php echo $item['quantity_available']; ?>)">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="equipment_id" 
                                                       value="<?php echo $item['id']; ?>" id="equipment<?php echo $item['id']; ?>" 
                                                       <?php echo (isset($_POST['equipment_id']) && $_POST['equipment_id'] == $item['id']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label w-100" for="equipment<?php echo $item['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                    <?php if ($item['model']): ?>
                                                    <br><small class="text-muted">Model: <?php echo htmlspecialchars($item['model']); ?></small>
                                                    <?php endif; ?>
                                                    <br><small class="text-info">Available: <?php echo $item['quantity_available']; ?> units</small>
                                                </label>
                                            </div>
                                            <span class="badge bg-success stock-badge">
                                                <?php echo $item['quantity_available']; ?> Available
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Borrowing Form -->
            <div class="col-lg-4">
                <div class="card sticky-top">
                    <div class="card-body">
                        <h5 class="text-primary-custom mb-4">
                            <i class="fas fa-clipboard-list me-2"></i>Borrowing Details
                        </h5>

                        <form method="POST" action="">
                            <!-- Selected Equipment Display -->
                            <div class="mb-3" id="selectedEquipmentDisplay" style="display: none;">
                                <label class="form-label">Selected Equipment</label>
                                <div class="bg-yellow-light p-2 rounded" id="selectedEquipmentInfo">
                                    <small>No equipment selected</small>
                                </div>
                                <input type="hidden" name="equipment_id" id="selectedEquipmentId" value="<?php echo isset($_POST['equipment_id']) ? $_POST['equipment_id'] : ''; ?>">
                            </div>

                            <!-- Quantity -->
                            <div class="mb-3">
                                <label class="form-label">Quantity *</label>
                                <select class="form-select" name="quantity" id="quantitySelect" disabled>
                                    <option value="">Select equipment first</option>
                                </select>
                            </div>

                            <!-- Purpose -->
                            <div class="mb-3">
                                <label class="form-label">Purpose *</label>
                                <textarea class="form-control" name="purpose" rows="3" required
                                          placeholder="Describe what you'll use this equipment for..."><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
                            </div>

                            <!-- Date Range -->
                            <div class="mb-3">
                                <label class="form-label">Borrowing Date *</label>
                                <input type="date" class="form-control" name="requested_from" required
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo isset($_POST['requested_from']) ? $_POST['requested_from'] : ''; ?>">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Return Date *</label>
                                <input type="date" class="form-control" name="requested_to" required
                                       min="<?php echo date('Y-m-d', strtotime('+0 day')); ?>"
                                       value="<?php echo isset($_POST['requested_to']) ? $_POST['requested_to'] : ''; ?>">
                            </div>

                            <!-- Student Info -->
                            <div class="mb-4">
                                <div class="bg-yellow-light p-3 rounded">
                                    <h6>Student Information</h6>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                                    <!-- <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($student['contact']); ?></p> -->
                                </div>
                            </div>
                            <button type="submit" name="submit_request" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="bg-dark text-white mt-5">
        <div class="container py-3 text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Hardware Lab System. All rights reserved.</p>
        </div>
    </footer>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to select equipment and update form
        function selectEquipment(id, name, available) {
            document.getElementById('selectedEquipmentId').value = id;
            document.getElementById('selectedEquipmentDisplay').style.display = 'block';
            document.getElementById('selectedEquipmentInfo').innerHTML = `<strong>${name}</strong> (${available} available)`;

            // Update quantity options
            const quantitySelect = document.getElementById('quantitySelect');
            quantitySelect.innerHTML = '';
            for (let i = 1; i <= available; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = `${i} ${i > 1 ? 'units' : 'unit'}`;
                quantitySelect.appendChild(option);
            }
            quantitySelect.disabled = false;
        }
        // Initialize quantity select
        document.addEventListener('DOMContentLoaded', function() {
            const quantitySelect = document.getElementById('quantitySelect');
            quantitySelect.innerHTML = '<option value="">Select equipment first</option>';
            quantitySelect.disabled = true;
        });
    </script>
</body>