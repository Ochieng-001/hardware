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

// Handle feedback submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_assistance_feedback'])) {
        $ticket_id = $_POST['ticket_id'];
        $rating = $_POST['rating'];
        $satisfaction = $_POST['satisfaction'];
        $response_time_rating = $_POST['response_time_rating'] ?? null;
        $service_quality_rating = $_POST['service_quality_rating'] ?? null;
        $staff_helpfulness_rating = $_POST['staff_helpfulness_rating'] ?? null;
        $comment = trim($_POST['comment']);
        $suggestions = trim($_POST['suggestions']);
        $would_recommend = isset($_POST['would_recommend']) ? 1 : 0;
        
        // Validate required fields
        if (empty($rating) || empty($satisfaction)) {
            $error = 'Please provide both overall rating and satisfaction level.';
        } else {
            // Check if feedback already exists
            $db->query("SELECT id FROM assistance_feedback WHERE ticket_id = :ticket_id AND student_id = :student_id");
            $db->bind(':ticket_id', $ticket_id);
            $db->bind(':student_id', $student_id);
            $existing = $db->single();
            
            if ($existing) {
                $error = 'You have already submitted feedback for this request.';
            } else {
                // Insert feedback
                $db->query("
                    INSERT INTO assistance_feedback (
                        ticket_id, student_id, rating, satisfaction, 
                        response_time_rating, service_quality_rating, staff_helpfulness_rating,
                        comment, suggestions, would_recommend, created_at
                    ) VALUES (
                        :ticket_id, :student_id, :rating, :satisfaction,
                        :response_time_rating, :service_quality_rating, :staff_helpfulness_rating,
                        :comment, :suggestions, :would_recommend, NOW()
                    )
                ");
                $db->bind(':ticket_id', $ticket_id);
                $db->bind(':student_id', $student_id);
                $db->bind(':rating', $rating);
                $db->bind(':satisfaction', $satisfaction);
                $db->bind(':response_time_rating', $response_time_rating);
                $db->bind(':service_quality_rating', $service_quality_rating);
                $db->bind(':staff_helpfulness_rating', $staff_helpfulness_rating);
                $db->bind(':comment', $comment);
                $db->bind(':suggestions', $suggestions);
                $db->bind(':would_recommend', $would_recommend);
                $db->execute();
                
                $success = 'Thank you! Your feedback has been submitted successfully.';
            }
        }
    }
    
    if (isset($_POST['submit_borrowing_feedback'])) {
        $request_id = $_POST['request_id'];
        $rating = $_POST['rating'];
        $satisfaction = $_POST['satisfaction'];
        $equipment_condition_rating = $_POST['equipment_condition_rating'] ?? null;
        $service_quality_rating = $_POST['service_quality_rating'] ?? null;
        $process_efficiency_rating = $_POST['process_efficiency_rating'] ?? null;
        $comment = trim($_POST['comment']);
        $equipment_issues = trim($_POST['equipment_issues']);
        $suggestions = trim($_POST['suggestions']);
        $would_recommend = isset($_POST['would_recommend']) ? 1 : 0;
        
        // Validate required fields
        if (empty($rating) || empty($satisfaction)) {
            $error = 'Please provide both overall rating and satisfaction level.';
        } else {
            // Check if feedback already exists
            $db->query("SELECT id FROM borrowing_feedback WHERE request_id = :request_id AND student_id = :student_id");
            $db->bind(':request_id', $request_id);
            $db->bind(':student_id', $student_id);
            $existing = $db->single();
            
            if ($existing) {
                $error = 'You have already submitted feedback for this request.';
            } else {
                // Insert feedback
                $db->query("
                    INSERT INTO borrowing_feedback (
                        request_id, student_id, rating, satisfaction,
                        equipment_condition_rating, service_quality_rating, process_efficiency_rating,
                        comment, equipment_issues, suggestions, would_recommend, created_at
                    ) VALUES (
                        :request_id, :student_id, :rating, :satisfaction,
                        :equipment_condition_rating, :service_quality_rating, :process_efficiency_rating,
                        :comment, :equipment_issues, :suggestions, :would_recommend, NOW()
                    )
                ");
                $db->bind(':request_id', $request_id);
                $db->bind(':student_id', $student_id);
                $db->bind(':rating', $rating);
                $db->bind(':satisfaction', $satisfaction);
                $db->bind(':equipment_condition_rating', $equipment_condition_rating);
                $db->bind(':service_quality_rating', $service_quality_rating);
                $db->bind(':process_efficiency_rating', $process_efficiency_rating);
                $db->bind(':comment', $comment);
                $db->bind(':equipment_issues', $equipment_issues);
                $db->bind(':suggestions', $suggestions);
                $db->bind(':would_recommend', $would_recommend);
                $db->execute();
                
                $success = 'Thank you! Your feedback has been submitted successfully.';
            }
        }
    }
}

// Get completed assistance tickets without feedback
$db->query("
    SELECT at.*, aty.name as assistance_type, a.full_name as assigned_to_name,
           af.id as feedback_exists
    FROM assistance_tickets at 
    LEFT JOIN assistance_types aty ON at.assistance_type_id = aty.id 
    LEFT JOIN admins a ON at.assigned_to = a.id 
    LEFT JOIN assistance_feedback af ON (at.id = af.ticket_id AND af.student_id = at.student_id)
    WHERE at.student_id = :student_id AND at.status = 'resolved'
    ORDER BY at.updated_at DESC
");
$db->bind(':student_id', $student_id);
$assistance_tickets = $db->resultSet();

// Get completed borrowing requests without feedback
$db->query("
    SELECT br.*, e.name as equipment_name, e.model, a.full_name as approved_by_name,
           bf.id as feedback_exists
    FROM borrowing_requests br 
    LEFT JOIN equipment e ON br.equipment_id = e.id 
    LEFT JOIN admins a ON br.approved_by = a.id 
    LEFT JOIN borrowing_feedback bf ON (br.id = bf.request_id AND bf.student_id = br.student_id)
    WHERE br.student_id = :student_id AND br.status = 'returned'
    ORDER BY br.updated_at DESC
");
$db->bind(':student_id', $student_id);
$borrowing_requests = $db->resultSet();

// Get submitted feedback for display
$db->query("
    SELECT af.*, at.ticket_number, at.title, aty.name as assistance_type
    FROM assistance_feedback af
    JOIN assistance_tickets at ON af.ticket_id = at.id
    LEFT JOIN assistance_types aty ON at.assistance_type_id = aty.id
    WHERE af.student_id = :student_id
    ORDER BY af.created_at DESC
");
$db->bind(':student_id', $student_id);
$submitted_assistance_feedback = $db->resultSet();

$db->query("
    SELECT bf.*, br.request_number, e.name as equipment_name, e.model
    FROM borrowing_feedback bf
    JOIN borrowing_requests br ON bf.request_id = br.id
    JOIN equipment e ON br.equipment_id = e.id
    WHERE bf.student_id = :student_id
    ORDER BY bf.created_at DESC
");
$db->bind(':student_id', $student_id);
$submitted_borrowing_feedback = $db->resultSet();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Hardware Lab Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-dark {
            background: linear-gradient(135deg, #0f00e6ff 0%, #0408dbff 100%);
        }
        .feedback-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .feedback-card:hover {
            border-left-color: #05079cff;
            box-shadow: 0 4px 15px rgba(3, 1, 131, 0.8);
        }
        .star-rating {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating label {
            cursor: pointer;
            color: #ddd;
            font-size: 1.5rem;
            transition: color 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #fabd06ff;
        }
        .star-rating input[type="radio"]:checked ~ label {
            color: #ffc107f8;
        }
        .rating-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .feedback-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .submitted-feedback {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }
        .rating-display {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .rating-stars {
            color: #ffc107;
        }
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            .feedback-section {
                padding: 1rem;
            }
            .star-rating label {
                font-size: 1.2rem;
            }
        }
    </style>
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
                    <a href="requests.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="fas fa-history"></i> <span class="d-none d-sm-inline">My Requests</span>
                    </a>
                    <a href="index.php?logout=1" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
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
                    <i class="fas fa-star me-2"></i>Service Feedback
                </h2>
                <p class="text-muted">Share your experience to help us improve our services</p>
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

        <!-- Pending Feedback Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Pending Feedback
                        </h4>
                        <small>Please provide feedback for your completed requests</small>
                    </div>
                    <div class="card-body">
                        <!-- Assistance Tickets Needing Feedback -->
                        <?php 
                        $pending_assistance = array_filter($assistance_tickets, function($ticket) {
                            return !$ticket['feedback_exists'];
                        });
                        ?>
                        
                        <?php if (!empty($pending_assistance)): ?>
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-tools me-2"></i>Technical Assistance
                        </h5>
                        <?php foreach ($pending_assistance as $ticket): ?>
                        <div class="feedback-card card mb-4">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">
                                            #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - 
                                            <?php echo htmlspecialchars($ticket['title']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($ticket['assistance_type'] ?? 'General'); ?> | 
                                            Resolved on <?php echo date('M d, Y', strtotime($ticket['updated_at'])); ?>
                                        </small>
                                    </div>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" 
                                            data-bs-target="#assistanceFeedback<?php echo $ticket['id']; ?>">
                                        <i class="fas fa-star"></i> Give Feedback
                                    </button>
                                </div>
                            </div>
                            
                            <div class="collapse" id="assistanceFeedback<?php echo $ticket['id']; ?>">
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="rating-label">Overall Rating *</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="rating" value="<?php echo $i; ?>" 
                                                           id="rating_<?php echo $ticket['id']; ?>_<?php echo $i; ?>" required>
                                                    <label for="rating_<?php echo $ticket['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                    <span class="ms-2 text-muted">Click to rate</span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Satisfaction Level *</label>
                                                <select name="satisfaction" class="form-select" required>
                                                    <option value="">Select satisfaction level</option>
                                                    <option value="very_satisfied">Very Satisfied</option>
                                                    <option value="satisfied">Satisfied</option>
                                                    <option value="neutral">Neutral</option>
                                                    <option value="dissatisfied">Dissatisfied</option>
                                                    <option value="very_dissatisfied">Very Dissatisfied</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="rating-label">Response Time</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="response_time_rating" value="<?php echo $i; ?>" 
                                                           id="response_time_<?php echo $ticket['id']; ?>_<?php echo $i; ?>">
                                                    <label for="response_time_<?php echo $ticket['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label class="rating-label">Service Quality</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="service_quality_rating" value="<?php echo $i; ?>" 
                                                           id="service_quality_<?php echo $ticket['id']; ?>_<?php echo $i; ?>">
                                                    <label for="service_quality_<?php echo $ticket['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label class="rating-label">Staff Helpfulness</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="staff_helpfulness_rating" value="<?php echo $i; ?>" 
                                                           id="staff_helpfulness_<?php echo $ticket['id']; ?>_<?php echo $i; ?>">
                                                    <label for="staff_helpfulness_<?php echo $ticket['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Comments</label>
                                            <textarea name="comment" class="form-control" rows="3" 
                                                      placeholder="Tell us about your experience..."></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Suggestions for Improvement</label>
                                            <textarea name="suggestions" class="form-control" rows="2" 
                                                      placeholder="How can we improve our service?"></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="would_recommend" value="1" 
                                                       class="form-check-input" id="recommend_<?php echo $ticket['id']; ?>">
                                                <label class="form-check-label" for="recommend_<?php echo $ticket['id']; ?>">
                                                    I would recommend this service to others
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end">
                                            <button type="submit" name="submit_assistance_feedback" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Borrowing Requests Needing Feedback -->
                        <?php 
                        $pending_borrowing = array_filter($borrowing_requests, function($request) {
                            return !$request['feedback_exists'];
                        });
                        ?>
                        
                        <?php if (!empty($pending_borrowing)): ?>
                        <h5 class="text-warning mb-3">
                            <i class="fas fa-handshake me-2"></i>Equipment Borrowing
                        </h5>
                        <?php foreach ($pending_borrowing as $request): ?>
                        <div class="feedback-card card mb-4">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">
                                            #<?php echo htmlspecialchars($request['request_number']); ?> - 
                                            <?php echo htmlspecialchars($request['equipment_name']); ?>
                                            <?php if ($request['model']): ?>
                                            (<?php echo htmlspecialchars($request['model']); ?>)
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            Returned on <?php echo date('M d, Y', strtotime($request['updated_at'])); ?>
                                        </small>
                                    </div>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="collapse" 
                                            data-bs-target="#borrowingFeedback<?php echo $request['id']; ?>">
                                        <i class="fas fa-star"></i> Give Feedback
                                    </button>
                                </div>
                            </div>
                            
                            <div class="collapse" id="borrowingFeedback<?php echo $request['id']; ?>">
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="rating-label">Overall Rating *</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="rating" value="<?php echo $i; ?>" 
                                                           id="b_rating_<?php echo $request['id']; ?>_<?php echo $i; ?>" required>
                                                    <label for="b_rating_<?php echo $request['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                    <span class="ms-2 text-muted">Click to rate</span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Satisfaction Level *</label>
                                                <select name="satisfaction" class="form-select" required>
                                                    <option value="">Select satisfaction level</option>
                                                    <option value="very_satisfied">Very Satisfied</option>
                                                    <option value="satisfied">Satisfied</option>
                                                    <option value="neutral">Neutral</option>
                                                    <option value="dissatisfied">Dissatisfied</option>
                                                    <option value="very_dissatisfied">Very Dissatisfied</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="rating-label">Equipment Condition</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="equipment_condition_rating" value="<?php echo $i; ?>" 
                                                           id="equipment_condition_<?php echo $request['id']; ?>_<?php echo $i; ?>">
                                                    <label for="equipment_condition_<?php echo $request['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label class="rating-label">Service Quality</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="service_quality_rating" value="<?php echo $i; ?>" 
                                                           id="b_service_quality_<?php echo $request['id']; ?>_<?php echo $i; ?>">
                                                    <label for="b_service_quality_<?php echo $request['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label class="rating-label">Process Efficiency</label>
                                                <div class="star-rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="process_efficiency_rating" value="<?php echo $i; ?>" 
                                                           id="process_efficiency_<?php echo $request['id']; ?>_<?php echo $i; ?>">
                                                    <label for="process_efficiency_<?php echo $request['id']; ?>_<?php echo $i; ?>">
                                                        <i class="fas fa-star"></i>
                                                    </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Comments</label>
                                            <textarea name="comment" class="form-control" rows="3" 
                                                      placeholder="Tell us about your borrowing experience..."></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Equipment Issues (if any)</label>
                                            <textarea name="equipment_issues" class="form-control" rows="2" 
                                                      placeholder="Did you encounter any issues with the equipment?"></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Suggestions for Improvement</label>
                                            <textarea name="suggestions" class="form-control" rows="2" 
                                                      placeholder="How can we improve our borrowing process?"></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="would_recommend" value="1" 
                                                       class="form-check-input" id="b_recommend_<?php echo $request['id']; ?>">
                                                <label class="form-check-label" for="b_recommend_<?php echo $request['id']; ?>">
                                                    I would recommend this borrowing service to others
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end">
                                            <button type="submit" name="submit_borrowing_feedback" class="btn btn-warning">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (empty($pending_assistance) && empty($pending_borrowing)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">All caught up!</h5>
                            <p class="text-muted">You have no pending feedback requests at this time.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submitted Feedback History -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Your Feedback History
                        </h4>
                        <small>Previously submitted feedback</small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($submitted_assistance_feedback) || !empty($submitted_borrowing_feedback)): ?>
                        
                        <!-- Submitted Assistance Feedback -->
                        <?php if (!empty($submitted_assistance_feedback)): ?>
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-tools me-2"></i>Technical Assistance Feedback
                        </h5>
                        <?php foreach ($submitted_assistance_feedback as $feedback): ?>
                        <div class="submitted-feedback">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">
                                        #<?php echo htmlspecialchars($feedback['ticket_number']); ?> - 
                                        <?php echo htmlspecialchars($feedback['title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($feedback['assistance_type'] ?? 'General'); ?> | 
                                        Submitted on <?php echo date('M d, Y H:i', strtotime($feedback['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="rating-display mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'rating-stars' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-2 fw-bold"><?php echo $feedback['rating']; ?>/5</span>
                                    </div>
                                    <span class="badge bg-info">
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
                            
                            <?php if ($feedback['would_recommend']): ?>
                            <div class="text-success">
                                <i class="fas fa-thumbs-up"></i> Would recommend to others
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Submitted Borrowing Feedback -->
                        <?php if (!empty($submitted_borrowing_feedback)): ?>
                        <h5 class="text-warning mb-3 <?php echo !empty($submitted_assistance_feedback) ? 'mt-4' : ''; ?>">
                            <i class="fas fa-handshake me-2"></i>Equipment Borrowing Feedback
                        </h5>
                        <?php foreach ($submitted_borrowing_feedback as $feedback): ?>
                        <div class="submitted-feedback">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">
                                        #<?php echo htmlspecialchars($feedback['request_number']); ?> - 
                                        <?php echo htmlspecialchars($feedback['equipment_name']); ?>
                                        <?php if ($feedback['model']): ?>
                                        (<?php echo htmlspecialchars($feedback['model']); ?>)
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        Submitted on <?php echo date('M d, Y H:i', strtotime($feedback['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="rating-display mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'rating-stars' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-2 fw-bold"><?php echo $feedback['rating']; ?>/5</span>
                                    </div>
                                    <span class="badge bg-info">
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
                            
                            <?php if ($feedback['would_recommend']): ?>
                            <div class="text-success">
                                <i class="fas fa-thumbs-up"></i> Would recommend to others
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-comments text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No feedback submitted yet</h5>
                            <p class="text-muted">Your feedback history will appear here once you submit feedback</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        
        // Star rating functionality
        document.querySelectorAll('.star-rating').forEach(function(rating) {
            const inputs = rating.querySelectorAll('input[type="radio"]');
            const labels = rating.querySelectorAll('label');
            
            labels.forEach(function(label, index) {
                label.addEventListener('mouseenter', function() {
                    // Highlight stars on hover
                    for (let i = 0; i <= index; i++) {
                        labels[i].style.color = '#ffc107';
                    }
                    for (let i = index + 1; i < labels.length; i++) {
                        labels[i].style.color = '#ddd';
                    }
                });
                
                label.addEventListener('click', function() {
                    // Set rating on click
                    inputs[index].checked = true;
                    updateStarDisplay(rating);
                });
            });
            
            rating.addEventListener('mouseleave', function() {
                // Reset to current selection on mouse leave
                updateStarDisplay(rating);
            });
        });
        
        function updateStarDisplay(rating) {
            const inputs = rating.querySelectorAll('input[type="radio"]');
            const labels = rating.querySelectorAll('label');
            let checkedIndex = -1;
            
            inputs.forEach(function(input, index) {
                if (input.checked) {
                    checkedIndex = index;
                }
            });
            
            labels.forEach(function(label, index) {
                if (index <= checkedIndex) {
                    label.style.color = '#ffc107';
                } else {
                    label.style.color = '#ddd';
                }
            });
        }
        
        // Initialize star displays
        document.querySelectorAll('.star-rating').forEach(updateStarDisplay);
        
        // Form validation
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const ratingInputs = form.querySelectorAll('input[name="rating"]');
                const satisfactionSelect = form.querySelector('select[name="satisfaction"]');
                
                let ratingSelected = false;
                ratingInputs.forEach(function(input) {
                    if (input.checked) {
                        ratingSelected = true;
                    }
                });
                
                if (!ratingSelected) {
                    e.preventDefault();
                    alert('Please select an overall rating before submitting.');
                    return false;
                }
                
                if (!satisfactionSelect.value) {
                    e.preventDefault();
                    alert('Please select a satisfaction level before submitting.');
                    return false;
                }
            });
        });
        
        // Smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Auto-expand feedback forms if there's an error
        <?php if ($error): ?>
        // Keep the last form expanded if there was an error
        const lastCollapse = document.querySelector('.collapse.show');
        if (lastCollapse) {
            lastCollapse.classList.add('show');
        }
        <?php endif; ?>
    </script>
</body>
</html>