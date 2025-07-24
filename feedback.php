<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get admin info
$db->query("SELECT * FROM admins WHERE id = :admin_id");
$db->bind(':admin_id', $admin_id);
$admin = $db->single();

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter settings
$feedback_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : '';
$satisfaction_filter = isset($_GET['satisfaction']) ? $_GET['satisfaction'] : '';

// Get assistance feedback with filters
$assistance_where = "1=1";
$assistance_params = [];

if ($rating_filter) {
    $assistance_where .= " AND af.rating = :rating";
    $assistance_params[':rating'] = $rating_filter;
}

if ($satisfaction_filter) {
    $assistance_where .= " AND af.satisfaction = :satisfaction";
    $assistance_params[':satisfaction'] = $satisfaction_filter;
}

$assistance_feedback = [];
if ($feedback_type === 'all' || $feedback_type === 'assistance') {
    $db->query("
        SELECT af.*, at.ticket_number, at.title, at.status,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.course, a.full_name as assigned_to_name,
               aty.name as assistance_type
        FROM assistance_feedback af
        JOIN assistance_tickets at ON af.ticket_id = at.id
        JOIN students s ON af.student_id = s.student_id
        LEFT JOIN admins a ON at.assigned_to = a.id
        LEFT JOIN assistance_types aty ON at.assistance_type_id = aty.id
        WHERE $assistance_where
        ORDER BY af.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($assistance_params as $key => $value) {
        $db->bind($key, $value);
    }
    $db->bind(':limit', $limit);
    $db->bind(':offset', $offset);
    $assistance_feedback = $db->resultSet();
}

// Get borrowing feedback with filters
$borrowing_where = "1=1";
$borrowing_params = [];

if ($rating_filter) {
    $borrowing_where .= " AND bf.rating = :rating";
    $borrowing_params[':rating'] = $rating_filter;
}

if ($satisfaction_filter) {
    $borrowing_where .= " AND bf.satisfaction = :satisfaction";
    $borrowing_params[':satisfaction'] = $satisfaction_filter;
}

$borrowing_feedback = [];
if ($feedback_type === 'all' || $feedback_type === 'borrowing') {
    $db->query("
        SELECT bf.*, br.request_number, br.status,
               e.name as equipment_name, e.model,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.course, a.full_name as approved_by_name
        FROM borrowing_feedback bf
        JOIN borrowing_requests br ON bf.request_id = br.id
        JOIN students s ON bf.student_id = s.student_id
        JOIN equipment e ON br.equipment_id = e.id
        LEFT JOIN admins a ON br.approved_by = a.id
        WHERE $borrowing_where
        ORDER BY bf.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($borrowing_params as $key => $value) {
        $db->bind($key, $value);
    }
    $db->bind(':limit', $limit);
    $db->bind(':offset', $offset);
    $borrowing_feedback = $db->resultSet();
}

// Get feedback statistics
$db->query("
    SELECT 
        COUNT(*) as total_assistance_feedback,
        AVG(rating) as avg_assistance_rating,
        SUM(CASE WHEN satisfaction IN ('satisfied', 'very_satisfied') THEN 1 ELSE 0 END) as satisfied_assistance,
        SUM(CASE WHEN would_recommend = 1 THEN 1 ELSE 0 END) as recommend_assistance
    FROM assistance_feedback
");
$assistance_stats = $db->single();

$db->query("
    SELECT 
        COUNT(*) as total_borrowing_feedback,
        AVG(rating) as avg_borrowing_rating,
        SUM(CASE WHEN satisfaction IN ('satisfied', 'very_satisfied') THEN 1 ELSE 0 END) as satisfied_borrowing,
        SUM(CASE WHEN would_recommend = 1 THEN 1 ELSE 0 END) as recommend_borrowing
    FROM borrowing_feedback
");
$borrowing_stats = $db->single();

// Helper functions
function renderStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star text-warning"></i>';
        } else {
            $stars .= '<i class="far fa-star text-muted"></i>';
        }
    }
    return $stars;
}

function getSatisfactionBadge($satisfaction) {
    switch($satisfaction) {
        case 'very_satisfied': return 'bg-success';
        case 'satisfied': return 'bg-info';
        case 'neutral': return 'bg-warning text-dark';
        case 'dissatisfied': return 'bg-danger';
        case 'very_dissatisfied': return 'bg-dark';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Hardware Lab Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-dark {
            background: linear-gradient(135deg, #0221aaff 0%, #0602f5ff 100%);
        }
        .stat-card {
            border-left: 4px solid #1a03e9ff;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .feedback-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .feedback-card:hover {
            border-left-color: #0300cfff;
            box-shadow: 0 4px 15px rgba(0,123,255,0.1);
        }
        .rating-display {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-tools me-2"></i>Hardware Lab Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text text-white me-3">
                        Welcome, <?php echo htmlspecialchars($admin['full_name']); ?>!
                    </span>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-primary">
                    <i class="fas fa-star me-2"></i>Feedback Management
                </h2>
                <p class="text-muted">Monitor and analyze student feedback for continuous improvement</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Assistance Feedback</h6>
                                <h3 class="text-primary"><?php echo $assistance_stats['total_assistance_feedback']; ?></h3>
                            </div>
                            <div class="text-primary">
                                <i class="fas fa-tools fa-2x"></i>
                            </div>
                        </div>
                        <small class="text-success">
                            Avg Rating: <?php echo number_format($assistance_stats['avg_assistance_rating'], 1); ?>/5
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Borrowing Feedback</h6>
                                <h3 class="text-warning"><?php echo $borrowing_stats['total_borrowing_feedback']; ?></h3>
                            </div>
                            <div class="text-warning">
                                <i class="fas fa-handshake fa-2x"></i>
                            </div>
                        </div>
                        <small class="text-success">
                            Avg Rating: <?php echo number_format($borrowing_stats['avg_borrowing_rating'], 1); ?>/5
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Satisfied Users</h6>
                                <h3 class="text-success">
                                    <?php 
                                    $total_satisfied = $assistance_stats['satisfied_assistance'] + $borrowing_stats['satisfied_borrowing'];
                                    echo $total_satisfied; 
                                    ?>
                                </h3>
                            </div>
                            <div class="text-success">
                                <i class="fas fa-smile fa-2x"></i>
                            </div>
                        </div>
                        <small class="text-muted">Very satisfied + Satisfied</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Recommendations</h6>
                                <h3 class="text-info">
                                    <?php 
                                    $total_recommendations = $assistance_stats['recommend_assistance'] + $borrowing_stats['recommend_borrowing'];
                                    echo $total_recommendations; 
                                    ?>
                                </h3>
                            </div>
                            <div class="text-info">
                                <i class="fas fa-thumbs-up fa-2x"></i>
                            </div>
                        </div>
                        <small class="text-muted">Would recommend</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Feedback Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?php echo $feedback_type === 'all' ? 'selected' : ''; ?>>All Feedback</option>
                            <option value="assistance" <?php echo $feedback_type === 'assistance' ? 'selected' : ''; ?>>Assistance Only</option>
                            <option value="borrowing" <?php echo $feedback_type === 'borrowing' ? 'selected' : ''; ?>>Borrowing Only</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select">
                            <option value="">All Ratings</option>
                            <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                            <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Satisfaction</label>
                        <select name="satisfaction" class="form-select">
                            <option value="">All Satisfaction Levels</option>
                            <option value="very_satisfied" <?php echo $satisfaction_filter === 'very_satisfied' ? 'selected' : ''; ?>>Very Satisfied</option>
                            <option value="satisfied" <?php echo $satisfaction_filter === 'satisfied' ? 'selected' : ''; ?>>Satisfied</option>
                            <option value="neutral" <?php echo $satisfaction_filter === 'neutral' ? 'selected' : ''; ?>>Neutral</option>
                            <option value="dissatisfied" <?php echo $satisfaction_filter === 'dissatisfied' ? 'selected' : ''; ?>>Dissatisfied</option>
                            <option value="very_dissatisfied" <?php echo $satisfaction_filter === 'very_dissatisfied' ? 'selected' : ''; ?>>Very Dissatisfied</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <a href="feedback.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assistance Feedback -->
        <?php if ($feedback_type === 'all' || $feedback_type === 'assistance'): ?>
        <?php if (!empty($assistance_feedback)): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>Technical Assistance Feedback
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($assistance_feedback as $feedback): ?>
                <div class="feedback-card card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="text-primary mb-1">
                                            #<?php echo htmlspecialchars($feedback['ticket_number']); ?> - 
                                            <?php echo htmlspecialchars($feedback['title']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($feedback['student_name']); ?> 
                                            (<?php echo htmlspecialchars($feedback['course']); ?>) | 
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($feedback['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="rating-display mb-1">
                                            <?php echo renderStars($feedback['rating']); ?>
                                            <span class="ms-2 fw-bold"><?php echo $feedback['rating']; ?>/5</span>
                                        </div>
                                        <span class="badge <?php echo getSatisfactionBadge($feedback['satisfaction']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $feedback['satisfaction'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($feedback['comment']): ?>
                                <div class="mb-2">
                                    <strong>Comment:</strong>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($feedback['comment'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($feedback['suggestions']): ?>
                                <div class="mb-2">
                                    <strong>Suggestions:</strong>
                                    <p class="mb-1 text-info"><?php echo nl2br(htmlspecialchars($feedback['suggestions'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="border-start ps-3">
                                    <h6 class="text-muted mb-2">Detailed Ratings</h6>
                                    
                                    <?php if ($feedback['response_time_rating']): ?>
                                    <div class="mb-1">
                                        <small>Response Time: </small>
                                        <?php echo renderStars($feedback['response_time_rating']); ?>
                                        <small class="text-muted">(<?php echo $feedback['response_time_rating']; ?>/5)</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['service_quality_rating']): ?>
                                    <div class="mb-1">
                                        <small>Service Quality: </small>
                                        <?php echo renderStars($feedback['service_quality_rating']); ?>
                                        <small class="text-muted">(<?php echo $feedback['service_quality_rating']; ?>/5)</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['staff_helpfulness_rating']): ?>
                                    <div class="mb-2">
                                        <small>Staff Helpfulness: </small>
                                        <?php echo renderStars($feedback['staff_helpfulness_rating']); ?>
                                        <small class="text-muted">(<?php echo $feedback['staff_helpfulness_rating']; ?>/5)</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['would_recommend']): ?>
                                    <div class="text-success">
                                        <i class="fas fa-thumbs-up"></i> Would recommend
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['assigned_to_name']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Handled by: <?php echo htmlspecialchars($feedback['assigned_to_name']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Borrowing Feedback -->
        <?php if ($feedback_type === 'all' || $feedback_type === 'borrowing'): ?>
        <?php if (!empty($borrowing_feedback)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-handshake me-2"></i>Equipment Borrowing Feedback
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($borrowing_feedback as $feedback): ?>
                <div class="feedback-card card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="text-warning mb-1">
                                            #<?php echo htmlspecialchars($feedback['request_number']); ?> - 
                                            <?php echo htmlspecialchars($feedback['equipment_name']); ?>
                                            <?php if ($feedback['model']): ?>
                                            (<?php echo htmlspecialchars($feedback['model']); ?>)
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($feedback['student_name']); ?> 
                                            (<?php echo htmlspecialchars($feedback['course']); ?>) | 
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($feedback['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="rating-display mb-1">
                                            <?php echo renderStars($feedback['rating']); ?>
                                            <span class="ms-2 fw-bold"><?php echo $feedback['rating']; ?>/5</span>
                                        </div>
                                        <span class="badge <?php echo getSatisfactionBadge($feedback['satisfaction']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $feedback['satisfaction'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($feedback['comment']): ?>
                                <div class="mb-2">
                                    <strong>Comment:</strong>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($feedback['comment'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($feedback['equipment_issues']): ?>
                                <div class="mb-2">
                                    <strong>Equipment Issues:</strong>
                                    <p class="mb-1 text-danger"><?php echo nl2br(htmlspecialchars($feedback['equipment_issues'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($feedback['suggestions']): ?>
                                <div class="mb-2">
                                    <strong>Suggestions:</strong>
                                    <p class="mb-1 text-info"><?php echo nl2br(htmlspecialchars($feedback['suggestions'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="border-start ps-3">
                                    <h6 class="text-muted mb-2">Detailed Ratings</h6>
                                    
                                    <?php if ($feedback['equipment_condition_rating']): ?>
                                    <div class="mb-1">
                                        <small>Equipment Condition: </small>
                                        <?php echo renderStars($feedback['equipment_condition_rating']); ?>
                                        <small class="text-muted">(<?php echo $feedback['equipment_condition_rating']; ?>/5)</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['service_quality_rating']): ?>
                                    <div class="mb-1">
                                        <small>Service Quality: </small>
                                        <?php echo renderStars($feedback['service_quality_rating']); ?>
                                        <small class="text-muted">(<?php echo $feedback['service_quality_rating']; ?>/5)</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['process_efficiency_rating']): ?>
                                    <div class="mb-2">
                                        <small>Process Efficiency: </small>
                                        <?php echo renderStars($feedback['process_efficiency_rating']); ?>
                                        <small class="text-muted">(<?php echo $feedback['process_efficiency_rating']; ?>/5)</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['would_recommend']): ?>
                                    <div class="text-success">
                                        <i class="fas fa-thumbs-up"></i> Would recommend
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($feedback['approved_by_name']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Approved by: <?php echo htmlspecialchars($feedback['approved_by_name']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- No feedback message -->
        <?php if (empty($assistance_feedback) && empty($borrowing_feedback)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
                <h4 class="text-muted mt-3">No feedback found</h4>
                <p class="text-muted">No feedback matches your current filter criteria.</p>
                <a href="feedback.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i> View All Feedback
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p class="text-muted mb-0">
                <i class="fas fa-tools me-2"></i>Hardware Lab Management System - Admin Panel
            </p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>