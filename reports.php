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

// Get date range for reports (default to last 30 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'overview';

// Overview Statistics - FIXED to work with existing config.php
function getOverviewStats($db, $start_date, $end_date) {
    $stats = [];
    
    // Tickets created in period
    $sql = "SELECT COUNT(*) as count FROM assistance_tickets WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $db->query($sql);
    $result = $db->single();
    $stats['tickets_created'] = $result ? $result['count'] : 0;
    
    // Tickets resolved in period
    $sql = "SELECT COUNT(*) as count FROM assistance_tickets WHERE DATE(resolved_at) BETWEEN '$start_date' AND '$end_date'";
    $db->query($sql);
    $result = $db->single();
    $stats['tickets_resolved'] = $result ? $result['count'] : 0;
    
    // Equipment borrowed in period
    $sql = "SELECT COUNT(*) as count FROM borrowing_requests WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    $db->query($sql);
    $result = $db->single();
    $stats['equipment_borrowed'] = $result ? $result['count'] : 0;
    
    // Equipment returned in period
    $sql = "SELECT COUNT(*) as count FROM borrowing_requests WHERE DATE(returned_at) BETWEEN '$start_date' AND '$end_date'";
    $db->query($sql);
    $result = $db->single();
    $stats['equipment_returned'] = $result ? $result['count'] : 0;
    
    // Current overdue items - NO DATE PARAMETERS
    $sql = "SELECT COUNT(*) as count FROM borrowing_requests WHERE status = 'overdue'";
    $db->query($sql);
    $result = $db->single();
    $stats['overdue_items'] = $result ? $result['count'] : 0;
    
    // Active users (students who created tickets or borrowed equipment in period)
    $sql = "SELECT COUNT(DISTINCT student_id) as count FROM (
                SELECT student_id FROM assistance_tickets WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                UNION
                SELECT student_id FROM borrowing_requests WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
            ) as active_users";
    $db->query($sql);
    $result = $db->single();
    $stats['active_users'] = $result ? $result['count'] : 0;
    
    return $stats;
}

// Ticket Analytics - FIXED
function getTicketAnalytics($db, $start_date, $end_date) {
    $analytics = [];
    
    // Tickets by status
    $sql = "SELECT status, COUNT(*) as count 
            FROM assistance_tickets 
            WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY status
            ORDER BY count DESC";
    $db->query($sql);
    $analytics['by_status'] = $db->resultSet();
    
    // Tickets by priority
    $sql = "SELECT priority, COUNT(*) as count 
            FROM assistance_tickets 
            WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY priority
            ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low')";
    $db->query($sql);
    $analytics['by_priority'] = $db->resultSet();
    
    // Tickets by assistance type
    $sql = "SELECT at.name, COUNT(*) as count 
            FROM assistance_tickets t
            LEFT JOIN assistance_types at ON t.assistance_type_id = at.id
            WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY t.assistance_type_id
            ORDER BY count DESC
            LIMIT 10";
    $db->query($sql);
    $analytics['by_type'] = $db->resultSet();
    
    // Average resolution time (in hours)
    $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_resolution_hours
            FROM assistance_tickets 
            WHERE resolved_at IS NOT NULL 
            AND DATE(resolved_at) BETWEEN '$start_date' AND '$end_date'";
    $db->query($sql);
    $result = $db->single();
    $analytics['avg_resolution_time'] = round($result['avg_resolution_hours'] ?? 0, 2);
    
    // Tickets by admin
    $sql = "SELECT a.full_name, COUNT(*) as count 
            FROM assistance_tickets t
            LEFT JOIN admins a ON t.assigned_to = a.id
            WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
            AND t.assigned_to IS NOT NULL
            GROUP BY t.assigned_to
            ORDER BY count DESC";
    $db->query($sql);
    $analytics['by_admin'] = $db->resultSet();
    
    // Daily ticket creation trend
    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM assistance_tickets 
            WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY DATE(created_at)
            ORDER BY date";
    $db->query($sql);
    $analytics['daily_trend'] = $db->resultSet();
    
    return $analytics;
}

// Equipment Analytics - FIXED
function getEquipmentAnalytics($db, $start_date, $end_date) {
    $analytics = [];
    
    // Most borrowed equipment
    $sql = "SELECT e.name, e.model, COUNT(*) as borrow_count
            FROM borrowing_requests br
            LEFT JOIN equipment e ON br.equipment_id = e.id
            WHERE DATE(br.created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY br.equipment_id
            ORDER BY borrow_count DESC
            LIMIT 10";
    $db->query($sql);
    $analytics['most_borrowed'] = $db->resultSet();
    
    // Equipment by category usage
    $sql = "SELECT ec.name as category, COUNT(*) as borrow_count
            FROM borrowing_requests br
            LEFT JOIN equipment e ON br.equipment_id = e.id
            LEFT JOIN equipment_categories ec ON e.category_id = ec.id
            WHERE DATE(br.created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY ec.id
            ORDER BY borrow_count DESC";
    $db->query($sql);
    $analytics['by_category'] = $db->resultSet();
    
    // Borrowing request status distribution
    $sql = "SELECT status, COUNT(*) as count
            FROM borrowing_requests
            WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY status
            ORDER BY count DESC";
    $db->query($sql);
    $analytics['by_status'] = $db->resultSet();
    
    // Equipment utilization rate - NO DATE PARAMETERS (current status)
    $sql = "SELECT 
                e.name,
                e.total_quantity,
                e.quantity_available,
                (e.total_quantity - e.quantity_available) as currently_borrowed,
                ROUND(((e.total_quantity - e.quantity_available) / e.total_quantity) * 100, 2) as utilization_rate
            FROM equipment e
            WHERE e.total_quantity > 0
            ORDER BY utilization_rate DESC
            LIMIT 15";
    $db->query($sql);
    $analytics['utilization'] = $db->resultSet();
    
    // Average borrow duration
    $sql = "SELECT AVG(TIMESTAMPDIFF(DAY, borrowed_at, returned_at)) as avg_days
            FROM borrowing_requests
            WHERE returned_at IS NOT NULL
            AND DATE(returned_at) BETWEEN '$start_date' AND '$end_date'";
    $db->query($sql);
    $result = $db->single();
    $analytics['avg_borrow_duration'] = round($result['avg_days'] ?? 0, 1);
    
    return $analytics;
}

// Student Activity Analytics - FIXED
function getStudentAnalytics($db, $start_date, $end_date) {
    $analytics = [];
    
    // Most active students (by tickets)
    $sql = "SELECT s.first_name, s.last_name, s.student_id, s.course, COUNT(*) as ticket_count
            FROM assistance_tickets t
            LEFT JOIN students s ON t.student_id = s.student_id
            WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY t.student_id
            ORDER BY ticket_count DESC
            LIMIT 10";
    $db->query($sql);
    $analytics['most_active_tickets'] = $db->resultSet();
    
    // Most active students (by borrowing)
    $sql = "SELECT s.first_name, s.last_name, s.student_id, s.course, COUNT(*) as borrow_count
            FROM borrowing_requests br
            LEFT JOIN students s ON br.student_id = s.student_id
            WHERE DATE(br.created_at) BETWEEN '$start_date' AND '$end_date'
            GROUP BY br.student_id
            ORDER BY borrow_count DESC
            LIMIT 10";
    $db->query($sql);
    $analytics['most_active_borrowing'] = $db->resultSet();
    
    // Activity by course
     $sql = "SELECT 
            s.course,
            COUNT(DISTINCT t.id) as ticket_count,
            COUNT(DISTINCT br.id) as borrow_count,
            COUNT(DISTINCT s.student_id) as student_count
        FROM students s
        LEFT JOIN assistance_tickets t ON s.student_id = t.student_id AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
        LEFT JOIN borrowing_requests br ON s.student_id = br.student_id AND DATE(br.created_at) BETWEEN '$start_date' AND '$end_date'
        WHERE s.course IS NOT NULL
        GROUP BY s.course
        ORDER BY (COUNT(DISTINCT t.id) + COUNT(DISTINCT br.id)) DESC";
    $db->query($sql);
    $analytics['by_course'] = $db->resultSet();
    
    return $analytics;
}

// Get analytics data based on report type
$overview_stats = getOverviewStats($db, $start_date, $end_date);
$ticket_analytics = $report_type === 'tickets' || $report_type === 'overview' ? getTicketAnalytics($db, $start_date, $end_date) : null;
$equipment_analytics = $report_type === 'equipment' || $report_type === 'overview' ? getEquipmentAnalytics($db, $start_date, $end_date) : null;
$student_analytics = $report_type === 'students' || $report_type === 'overview' ? getStudentAnalytics($db, $start_date, $end_date) : null;

// Helper functions for styling
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
    <title>Reports & Analytics - Hardware Lab Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        :root {
            --primary-blue: #0a1497;
            --secondary-blue: #0a1497;
            --accent-yellow: #f0c209;
            --light-gray: #F8F9FA;
            --white: #FFFFFF;
        }
        
        body {
            background-color: var(--light-gray);
            font-family: 'Arial', sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .stats-card {
            border-left: 4px solid var(--accent-yellow);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border: none;
            border-radius: 25px;
        }
        
        .text-primary-custom {
            color: var(--primary-blue) !important;
        }
        
        .bg-yellow-light {
            background-color: rgba(241, 196, 15, 0.1);
        }
        
        .report-nav {
            background: var(--white);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
        }
        
        /* Responsive chart sizing for pie charts */
        .pie-chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        
        @media (min-width: 992px) {
            .pie-chart-container {
                height: 250px;
                display: flex;
                justify-content: center;
                align-items: center;
            }
        }
        
        @media (min-width: 1200px) {
            .pie-chart-container {
                height: 280px;
            }
        }
        
        .table th {
            border-top: none;
            color: var(--primary-blue);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
            </a>
            <div class="navbar-nav ms-auto">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($admin['name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="index.php"><i class="fas fa-home me-2"></i>Student Portal</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?logout=1"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <!-- Report Controls -->
        <div class="report-nav">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="report_type">
                        <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="tickets" <?php echo $report_type === 'tickets' ? 'selected' : ''; ?>>Tickets Only</option>
                        <option value="equipment" <?php echo $report_type === 'equipment' ? 'selected' : ''; ?>>Equipment Only</option>
                        <option value="students" <?php echo $report_type === 'students' ? 'selected' : ''; ?>>Students Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Overview Statistics -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-ticket-alt text-primary" style="font-size: 2rem;"></i>
                        <h3 class="text-primary-custom mt-2"><?php echo $overview_stats['tickets_created']; ?></h3>
                        <p class="text-muted mb-0">Tickets Created</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                        <h3 class="text-primary-custom mt-2"><?php echo $overview_stats['tickets_resolved']; ?></h3>
                        <p class="text-muted mb-0">Tickets Resolved</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-handshake text-info" style="font-size: 2rem;"></i>
                        <h3 class="text-primary-custom mt-2"><?php echo $overview_stats['equipment_borrowed']; ?></h3>
                        <p class="text-muted mb-0">Items Borrowed</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-undo text-warning" style="font-size: 2rem;"></i>
                        <h3 class="text-primary-custom mt-2"><?php echo $overview_stats['equipment_returned']; ?></h3>
                        <p class="text-muted mb-0">Items Returned</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                        <h3 class="text-primary-custom mt-2"><?php echo $overview_stats['overdue_items']; ?></h3>
                        <p class="text-muted mb-0">Overdue Items</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users text-secondary" style="font-size: 2rem;"></i>
                        <h3 class="text-primary-custom mt-2"><?php echo $overview_stats['active_users']; ?></h3>
                        <p class="text-muted mb-0">Active Users</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Analytics -->
        <?php if ($ticket_analytics): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Tickets by Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="pie-chart-container">
                            <canvas id="ticketStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Tickets by Priority</h5>
                    </div>
                    <div class="card-body">
                        <div class="pie-chart-container">
                            <canvas id="ticketPriorityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Top Assistance Types</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($ticket_analytics['by_type'])): ?>
                                        <?php foreach ($ticket_analytics['by_type'] as $type): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($type['name'] ?? 'General'); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $type['count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Performance Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-12 mb-3">
                                <h3 class="text-primary-custom"><?php echo $ticket_analytics['avg_resolution_time']; ?> hrs</h3>
                                <p class="text-muted">Average Resolution Time</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Admin</th>
                                        <th>Tickets Handled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($ticket_analytics['by_admin'])): ?>
                                        <?php foreach ($ticket_analytics['by_admin'] as $admin_stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($admin_stat['full_name']); ?></td>
                                            <td><span class="badge bg-success"><?php echo $admin_stat['count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Equipment Analytics -->
        <?php if ($equipment_analytics): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Most Borrowed Equipment</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>Model</th>
                                        <th>Times Borrowed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($equipment_analytics['most_borrowed'])): ?>
                                        <?php foreach ($equipment_analytics['most_borrowed'] as $equipment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($equipment['name']); ?></td>
                                            <td><?php echo htmlspecialchars($equipment['model']); ?></td>
                                            <td><span class="badge bg-info"><?php echo $equipment['borrow_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Usage by Category</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="equipmentCategoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Equipment Utilization</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>Total</th>
                                        <th>Available</th>
                                        <th>Utilization %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($equipment_analytics['utilization'])): ?>
                                        <?php foreach ($equipment_analytics['utilization'] as $util): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($util['name']); ?></td>
                                            <td><?php echo $util['total_quantity']; ?></td>
                                            <td><?php echo $util['quantity_available']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $util['utilization_rate']; ?>%">
                                                        <?php echo $util['utilization_rate']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Average Borrow Duration</h5>
                    </div>
                    <div class="card-body text-center">
                        <h3 class="text-primary-custom"><?php echo $equipment_analytics['avg_borrow_duration']; ?> days</h3>
                        <p class="text-muted">Average time items are borrowed</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Student Analytics -->
        <?php if ($student_analytics): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Most Active Students (Tickets)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Student ID</th>
                                        <th>Course</th>
                                        <th>Tickets</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($student_analytics['most_active_tickets'])): ?>
                                        <?php foreach ($student_analytics['most_active_tickets'] as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($student['course']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $student['ticket_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Most Active Students (Borrowing)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Student ID</th>
                                        <th>Course</th>
                                        <th>Borrows</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($student_analytics['most_active_borrowing'])): ?>
                                        <?php foreach ($student_analytics['most_active_borrowing'] as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($student['course']); ?></td>
                                            <td><span class="badge bg-info"><?php echo $student['borrow_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Activity by Course</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Tickets</th>
                                        <th>Borrows</th>
                                        <th>Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($student_analytics['by_course'])): ?>
                                        <?php foreach ($student_analytics['by_course'] as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['course']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $course['ticket_count']; ?></span></td>
                                            <td><span class="badge bg-info"><?php echo $course['borrow_count']; ?></span></td>
                                            <td><span class="badge bg-secondary"><?php echo $course['student_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ticket Daily Trend Chart -->
        <?php if ($ticket_analytics && !empty($ticket_analytics['daily_trend'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-yellow-light">
                        <h5 class="mb-0 text-primary-custom">Daily Ticket Creation Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="ticketDailyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Chart.js Scripts -->
    <script>
        // Tickets by Status
        <?php if ($ticket_analytics && !empty($ticket_analytics['by_status'])): ?>
        const ticketStatusCtx = document.getElementById('ticketStatusChart').getContext('2d');
        new Chart(ticketStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($ticket_analytics['by_status'], 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($ticket_analytics['by_status'], 'count')); ?>,
                    backgroundColor: [
                        '#f0c209', '#0a1497', '#28a745', '#dc3545', '#6c757d', '#17a2b8', '#ffc107'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Tickets by Priority
        <?php if ($ticket_analytics && !empty($ticket_analytics['by_priority'])): ?>
        const ticketPriorityCtx = document.getElementById('ticketPriorityChart').getContext('2d');
        new Chart(ticketPriorityCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($ticket_analytics['by_priority'], 'priority')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($ticket_analytics['by_priority'], 'count')); ?>,
                    backgroundColor: [
                        '#6c757d', '#0a1497', '#f0c209', '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Equipment by Category
        <?php if ($equipment_analytics && !empty($equipment_analytics['by_category'])): ?>
        const equipmentCategoryCtx = document.getElementById('equipmentCategoryChart').getContext('2d');
        new Chart(equipmentCategoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($equipment_analytics['by_category'], 'category')); ?>,
                datasets: [{
                    label: 'Borrows',
                    data: <?php echo json_encode(array_column($equipment_analytics['by_category'], 'borrow_count')); ?>,
                    backgroundColor: '#0a1497'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true },
                    y: { beginAtZero: true }
                }
            }
        });
        <?php endif; ?>

        // Ticket Daily Trend
        <?php if ($ticket_analytics && !empty($ticket_analytics['daily_trend'])): ?>
        const ticketDailyTrendCtx = document.getElementById('ticketDailyTrendChart').getContext('2d');
        new Chart(ticketDailyTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($ticket_analytics['daily_trend'], 'date')); ?>,
                datasets: [{
                    label: 'Tickets Created',
                    data: <?php echo json_encode(array_column($ticket_analytics['daily_trend'], 'count')); ?>,
                    backgroundColor: 'rgba(10, 20, 151, 0.2)',
                    borderColor: '#0a1497',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true },
                    y: { beginAtZero: true }
                }
            }
        });
        <?php endif; ?>
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>