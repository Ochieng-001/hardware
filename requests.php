<?php
require_once 'config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$error = '';
$success = '';

// Get student info
$db->query("SELECT * FROM students WHERE student_id = :student_id");
$db->bind(':student_id', $student_id);
$student = $db->single();

// Handle request cancellation
if (isset($_POST['cancel_assistance']) && isset($_POST['ticket_id'])) {
    $ticket_id = $_POST['ticket_id'];
    
    // Check if ticket belongs to student and can be cancelled
    $db->query("SELECT * FROM assistance_tickets WHERE id = :ticket_id AND student_id = :student_id AND status IN ('pending', 'assigned')");
    $db->bind(':ticket_id', $ticket_id);
    $db->bind(':student_id', $student_id);
    $ticket = $db->single();
    
    if ($ticket) {
        $db->query("UPDATE assistance_tickets SET status = 'cancelled' WHERE id = :ticket_id");
        $db->bind(':ticket_id', $ticket_id);
        $db->execute();
        $success = 'Assistance request cancelled successfully.';
    } else {
        $error = 'Cannot cancel this request.';
    }
}

if (isset($_POST['cancel_borrowing']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    
    // Check if request belongs to student and can be cancelled
    $db->query("SELECT * FROM borrowing_requests WHERE id = :request_id AND student_id = :student_id AND status = 'pending'");
    $db->bind(':request_id', $request_id);
    $db->bind(':student_id', $student_id);
    $request = $db->single();
    
    if ($request) {
        $db->query("UPDATE borrowing_requests SET status = 'rejected' WHERE id = :request_id");
        $db->bind(':request_id', $request_id);
        $db->execute();
        $success = 'Borrowing request cancelled successfully.';
    } else {
        $error = 'Cannot cancel this request.';
    }
}

// Get assistance tickets
$db->query("
    SELECT at.*, aty.name as assistance_type, a.full_name as assigned_to_name 
    FROM assistance_tickets at 
    LEFT JOIN assistance_types aty ON at.assistance_type_id = aty.id 
    LEFT JOIN admins a ON at.assigned_to = a.id 
    WHERE at.student_id = :student_id 
    ORDER BY at.created_at DESC
");
$db->bind(':student_id', $student_id);
$assistance_tickets = $db->resultSet();

// Get borrowing requests
$db->query("
    SELECT br.*, e.name as equipment_name, e.model, a.full_name as approved_by_name 
    FROM borrowing_requests br 
    LEFT JOIN equipment e ON br.equipment_id = e.id 
    LEFT JOIN admins a ON br.approved_by = a.id 
    WHERE br.student_id = :student_id 
    ORDER BY br.created_at DESC
");
$db->bind(':student_id', $student_id);
$borrowing_requests = $db->resultSet();

// Helper function to get status badge class
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

// Helper function to get priority badge class
function getPriorityBadge($priority) {
    switch($priority) {
        case 'low': return 'bg-secondary';
        case 'medium': return 'bg-primary';
        case 'high': return 'bg-warning text-dark';
        case 'urgent': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - Hardware Lab Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="requests.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tools me-2"></i>Hardware Lab System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text text-white me-3 d-none d-md-block">
                        Welcome, <?php echo htmlspecialchars($student['first_name']); ?>!
                    </span>
                    <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="fas fa-home"></i> <span class="d-none d-sm-inline">Dashboard</span>
                    </a>
                    <a href="index.php?logout=1" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-3 mt-md-5">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                    <h2 class="text-primary-custom mb-3 mb-md-0">
                        <i class="fas fa-history me-2"></i>My Requests
                    </h2>
                    <div class="btn-group-mobile d-md-block">
                        <a href="index.php" class="btn btn-primary me-md-2 mb-2 mb-md-0">
                            <i class="fas fa-home"></i> Back Home
                        </a>
                        <a href="assistance.php" class="btn btn-primary me-md-2 mb-2 mb-md-0">
                            <i class="fas fa-plus"></i> New Assistance
                        </a>
                        <a href="borrowing.php" class="btn btn-warning">
                            <i class="fas fa-plus"></i> New Borrowing
                        </a>
                    </div>
                </div>
            </div>
        </div>

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

        <!-- Assistance Tickets -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h4 class="mb-0 text-primary-custom">
                            <i class="fas fa-tools me-2"></i>Technical Assistance Requests
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assistance_tickets)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No assistance requests yet</h5>
                            <p class="text-muted">Click "New Assistance" to create your first request</p>
                        </div>
                        <?php else: ?>
                        
                        <!-- Desktop Table -->
                        <div class="desktop-table">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Type</th>
                                            <th>Title</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Assigned To</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assistance_tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($ticket['assistance_type'] ?? 'General'); ?></td>
                                            <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                            <td>
                                                <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $ticket['assigned_to_name'] ?: 'Not assigned'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                        data-bs-target="#ticketModal<?php echo $ticket['id']; ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if (in_array($ticket['status'], ['pending', 'assigned'])): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this request?')">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                    <button type="submit" name="cancel_assistance" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Mobile Cards -->
                        <div class="mobile-card">
                            <?php foreach ($assistance_tickets as $ticket): ?>
                            <div class="mobile-request-card">
                                <div class="mobile-request-header">
                                    <div>
                                        <div class="fw-bold text-primary">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($ticket['assistance_type'] ?? 'General'); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?> mb-1">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                        <br>
                                        <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mobile-request-details">
                                    <div>
                                        <strong>Title:</strong> <?php echo htmlspecialchars($ticket['title']); ?>
                                    </div>
                                    <div>
                                        <strong>Assigned To:</strong> <?php echo $ticket['assigned_to_name'] ?: 'Not assigned'; ?>
                                    </div>
                                    <div>
                                        <strong>Created:</strong> <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="mobile-request-actions">
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" 
                                            data-bs-target="#ticketModal<?php echo $ticket['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if (in_array($ticket['status'], ['pending', 'assigned'])): ?>
                                    <form method="POST" class="flex-fill" onsubmit="return confirm('Are you sure you want to cancel this request?')">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <button type="submit" name="cancel_assistance" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Borrowing Requests -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h4 class="mb-0 text-primary-custom">
                            <i class="fas fa-handshake me-2"></i>Equipment Borrowing Requests
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($borrowing_requests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No borrowing requests yet</h5>
                            <p class="text-muted">Click "New Borrowing" to create your first request</p>
                        </div>
                        <?php else: ?>
                        
                        <!-- Desktop Table -->
                        <div class="desktop-table">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Request #</th>
                                            <th>Equipment</th>
                                            <th>Quantity</th>
                                            <th>Period</th>
                                            <th>Status</th>
                                            <th>Approved By</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($borrowing_requests as $request): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold"><?php echo htmlspecialchars($request['request_number']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($request['equipment_name']); ?>
                                                <?php if ($request['model']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($request['model']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $request['quantity_requested']; ?></td>
                                            <td>
                                                <?php echo date('M d', strtotime($request['requested_from'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($request['requested_to'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadge($request['status']); ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $request['approved_by_name'] ?: 'N/A'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                        data-bs-target="#borrowingModal<?php echo $request['id']; ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this request?')">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <button type="submit" name="cancel_borrowing" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Mobile Cards -->
                        <div class="mobile-card">
                            <?php foreach ($borrowing_requests as $request): ?>
                            <div class="mobile-request-card">
                                <div class="mobile-request-header">
                                    <div>
                                        <div class="fw-bold text-primary">#<?php echo htmlspecialchars($request['request_number']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($request['equipment_name']); ?></div>
                                        <?php if ($request['model']): ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($request['model']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo getStatusBadge($request['status']); ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mobile-request-details">
                                    <div>
                                        <strong>Quantity:</strong> <?php echo $request['quantity_requested']; ?>
                                    </div>
                                    <div>
                                        <strong>Period:</strong> 
                                        <?php echo date('M d', strtotime($request['requested_from'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($request['requested_to'])); ?>
                                    </div>
                                    <div>
                                        <strong>Approved By:</strong> <?php echo $request['approved_by_name'] ?: 'N/A'; ?>
                                    </div>
                                    <div>
                                        <strong>Created:</strong> <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="mobile-request-actions">
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" 
                                            data-bs-target="#borrowingModal<?php echo $request['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($request['status'] === 'pending'): ?>
                                    <form method="POST" class="flex-fill" onsubmit="return confirm('Are you sure you want to cancel this request?')">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="cancel_borrowing" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assistance Ticket Modals -->
    <?php foreach ($assistance_tickets as $ticket): ?>
    <div class="modal fade" id="ticketModal<?php echo $ticket['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-ticket-alt me-2"></i>
                        Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Type</div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['assistance_type'] ?? 'General'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Priority</div>
                            <div class="info-value">
                                <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Created</div>
                            <div class="info-value"><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Assigned To</div>
                            <div class="info-value"><?php echo $ticket['assigned_to_name'] ?: 'Not assigned'; ?></div>
                        </div>
                        <?php if ($ticket['scheduled_date']): ?>
                        <div class="info-item">
                            <div class="info-label">Scheduled</div>
                            <div class="info-value">
                                <?php echo date('M d, Y', strtotime($ticket['scheduled_date'])); ?>
                                <?php if ($ticket['scheduled_time']): ?>
                                at <?php echo date('H:i', strtotime($ticket['scheduled_time'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="description-section">
                        <div class="section-title">
                            <i class="fas fa-heading me-2"></i>Title
                        </div>
                        <p class="mb-3"><?php echo htmlspecialchars($ticket['title']); ?></p>
                        
                        <div class="section-title">
                            <i class="fas fa-align-left me-2"></i>Description
                        </div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Borrowing Request Modals -->
    <?php foreach ($borrowing_requests as $request): ?>
    <div class="modal fade" id="borrowingModal<?php echo $request['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-handshake me-2"></i>
                        Request #<?php echo htmlspecialchars($request['request_number']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Equipment</div>
                            <div class="info-value"><?php echo htmlspecialchars($request['equipment_name']); ?></div>
                        </div>
                        <?php if ($request['model']): ?>
                        <div class="info-item">
                            <div class="info-label">Model</div>
                            <div class="info-value"><?php echo htmlspecialchars($request['model']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <div class="info-label">Quantity</div>
                            <div class="info-value"><?php echo $request['quantity_requested']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="badge <?php echo getStatusBadge($request['status']); ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">From Date</div>
                            <div class="info-value"><?php echo date('M d, Y', strtotime($request['requested_from'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">To Date</div>
                            <div class="info-value"><?php echo date('M d, Y', strtotime($request['requested_to'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Created</div>
                            <div class="info-value"><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></div>
                        </div>
                        <?php if ($request['approved_by_name']): ?>
                        <div class="info-item">
                            <div class="info-label">Approved By</div>
                            <div class="info-value"><?php echo htmlspecialchars($request['approved_by_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($request['due_date']): ?>
                        <div class="info-item">
                            <div class="info-label">Due Date</div>
                            <div class="info-value"><?php echo date('M d, Y', strtotime($request['due_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="description-section">
                        <div class="section-title">
                            <i class="fas fa-bullseye me-2"></i>Purpose
                        </div>
                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($request['purpose'])); ?></p>
                        
                        <?php if ($request['notes']): ?>
                        <div class="section-title">
                            <i class="fas fa-sticky-note me-2"></i>Notes
                        </div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p class="text-muted mb-0">
                <i class="fas fa-tools me-2"></i>Hardware Lab Management System
            </p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
        
        // Smooth scroll for mobile navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>