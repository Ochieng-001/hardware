-- Hardware Lab Management System Database Schema

-- Students table (assuming it already exists)
CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    course VARCHAR(100),
    year_of_study INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin users table
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'lab_technician') DEFAULT 'lab_technician',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment categories
CREATE TABLE equipment_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment inventory
CREATE TABLE equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100),
    serial_number VARCHAR(100) UNIQUE,
    status ENUM('available', 'borrowed', 'maintenance', 'damaged') DEFAULT 'available',
    quantity_available INT DEFAULT 1,
    total_quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES equipment_categories(id)
);

-- Assistance request types
CREATE TABLE assistance_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    estimated_duration INT DEFAULT 30, -- in minutes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assistance tickets
CREATE TABLE assistance_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    assistance_type_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'assigned', 'in_progress', 'resolved', 'cancelled') DEFAULT 'pending',
    assigned_to INT NULL,
    scheduled_date DATE NULL,
    scheduled_time TIME NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (assistance_type_id) REFERENCES assistance_types(id),
    FOREIGN KEY (assigned_to) REFERENCES admins(id)
);

-- Equipment borrowing requests
CREATE TABLE borrowing_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_number VARCHAR(20) UNIQUE NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    equipment_id INT,
    quantity_requested INT DEFAULT 1,
    purpose TEXT NOT NULL,
    requested_from DATE NOT NULL,
    requested_to DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'active', 'returned', 'overdue') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    borrowed_at TIMESTAMP NULL,
    due_date TIMESTAMP NULL,
    returned_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    FOREIGN KEY (approved_by) REFERENCES admins(id)
);

-- Ticket comments/updates
CREATE TABLE ticket_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT,
    admin_id INT NULL,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES assistance_tickets(id),
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);


-- Feedback tables for Hardware Lab Management System

-- Table for assistance ticket feedback
CREATE TABLE assistance_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    satisfaction ENUM('very_dissatisfied', 'dissatisfied', 'neutral', 'satisfied', 'very_satisfied') NOT NULL,
    response_time_rating INT CHECK (response_time_rating >= 1 AND response_time_rating <= 5),
    service_quality_rating INT CHECK (service_quality_rating >= 1 AND service_quality_rating <= 5),
    staff_helpfulness_rating INT CHECK (staff_helpfulness_rating >= 1 AND staff_helpfulness_rating <= 5),
    comment TEXT,
    suggestions TEXT,
    would_recommend BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES assistance_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_ticket_feedback (ticket_id, student_id)
);

-- Table for borrowing request feedback
CREATE TABLE borrowing_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    satisfaction ENUM('very_dissatisfied', 'dissatisfied', 'neutral', 'satisfied', 'very_satisfied') NOT NULL,
    equipment_condition_rating INT CHECK (equipment_condition_rating >= 1 AND equipment_condition_rating <= 5),
    service_quality_rating INT CHECK (service_quality_rating >= 1 AND service_quality_rating <= 5),
    process_efficiency_rating INT CHECK (process_efficiency_rating >= 1 AND process_efficiency_rating <= 5),
    comment TEXT,
    suggestions TEXT,
    equipment_issues TEXT,
    would_recommend BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES borrowing_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_request_feedback (request_id, student_id)
);

-- Index for better performance
CREATE INDEX idx_assistance_feedback_student ON assistance_feedback(student_id);
CREATE INDEX idx_assistance_feedback_rating ON assistance_feedback(rating);
CREATE INDEX idx_assistance_feedback_created ON assistance_feedback(created_at);

CREATE INDEX idx_borrowing_feedback_student ON borrowing_feedback(student_id);
CREATE INDEX idx_borrowing_feedback_rating ON borrowing_feedback(rating);
CREATE INDEX idx_borrowing_feedback_created ON borrowing_feedback(created_at);

-- View for assistance feedback summary
CREATE VIEW assistance_feedback_summary AS
SELECT 
    af.ticket_id,
    at.ticket_number,
    at.title,
    af.rating,
    af.satisfaction,
    af.response_time_rating,
    af.service_quality_rating,
    af.staff_helpfulness_rating,
    af.comment,
    af.would_recommend,
    af.created_at as feedback_date,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.course,
    a.full_name as assigned_to_name
FROM assistance_feedback af
JOIN assistance_tickets at ON af.ticket_id = at.id
JOIN students s ON af.student_id = s.student_id
LEFT JOIN admins a ON at.assigned_to = a.id;

-- View for borrowing feedback summary
CREATE VIEW borrowing_feedback_summary AS
SELECT 
    bf.request_id,
    br.request_number,
    e.name as equipment_name,
    e.model,
    bf.rating,
    bf.satisfaction,
    bf.equipment_condition_rating,
    bf.service_quality_rating,
    bf.process_efficiency_rating,
    bf.comment,
    bf.equipment_issues,
    bf.would_recommend,
    bf.created_at as feedback_date,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.course,
    a.full_name as approved_by_name
FROM borrowing_feedback bf
JOIN borrowing_requests br ON bf.request_id = br.id
JOIN students s ON bf.student_id = s.student_id
JOIN equipment e ON br.equipment_id = e.id
LEFT JOIN admins a ON br.approved_by = a.id;


-- Database updates to support admin-created requests (phone requests)

-- Add fields to assistance_tickets table for phone requests
ALTER TABLE assistance_tickets 
ADD COLUMN phone_request BOOLEAN DEFAULT FALSE COMMENT 'Indicates if this request was made via phone call',
ADD COLUMN caller_notes TEXT COMMENT 'Admin notes about the phone call';

-- Add fields to borrowing_requests table for phone requests  
ALTER TABLE borrowing_requests 
ADD COLUMN phone_request BOOLEAN DEFAULT FALSE COMMENT 'Indicates if this request was made via phone call',
ADD COLUMN caller_notes TEXT COMMENT 'Admin notes about the phone call';

-- Add index for phone requests for better performance
CREATE INDEX idx_assistance_tickets_phone ON assistance_tickets(phone_request);
CREATE INDEX idx_borrowing_requests_phone ON borrowing_requests(phone_request);

-- Update the views to include phone request information
DROP VIEW IF EXISTS assistance_feedback_summary;
CREATE VIEW assistance_feedback_summary AS
SELECT 
    af.ticket_id,
    at.ticket_number,
    at.title,
    at.phone_request,
    at.caller_notes,
    af.rating,
    af.satisfaction,
    af.response_time_rating,
    af.service_quality_rating,
    af.staff_helpfulness_rating,
    af.comment,
    af.would_recommend,
    af.created_at as feedback_date,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.course,
    a.full_name as assigned_to_name
FROM assistance_feedback af
JOIN assistance_tickets at ON af.ticket_id = at.id
JOIN students s ON af.student_id = s.student_id
LEFT JOIN admins a ON at.assigned_to = a.id;

DROP VIEW IF EXISTS borrowing_feedback_summary;
CREATE VIEW borrowing_feedback_summary AS
SELECT 
    bf.request_id,
    br.request_number,
    br.phone_request,
    br.caller_notes,
    e.name as equipment_name,
    e.model,
    bf.rating,
    bf.satisfaction,
    bf.equipment_condition_rating,
    bf.service_quality_rating,
    bf.process_efficiency_rating,
    bf.comment,
    bf.equipment_issues,
    bf.would_recommend,
    bf.created_at as feedback_date,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.course,
    a.full_name as approved_by_name
FROM borrowing_feedback bf
JOIN borrowing_requests br ON bf.request_id = br.id
JOIN students s ON bf.student_id = s.student_id
JOIN equipment e ON br.equipment_id = e.id
LEFT JOIN admins a ON br.approved_by = a.id;



-- Insert sample data

-- Equipment Categories
INSERT INTO equipment_categories (name, description) VALUES
('Network Equipment', 'Routers, switches, cables and networking devices'),
('Cables', 'Ethernet, USB, HDMI and other cables'),
('Tools', 'Network testing tools and diagnostic equipment');

-- Equipment Items
INSERT INTO equipment (category_id, name, model, serial_number, total_quantity, quantity_available) VALUES
(1, 'Cisco Switch 24-Port', 'WS-C2960-24TT-L', 'SW001', 5, 5),
(1, 'Cisco Router', '2811', 'RT001', 3, 3),
(1, 'TP-Link Wireless Router', 'AC1750', 'WR001', 8, 8),
(2, 'Ethernet Cable Cat6', 'Standard', 'ETH001', 50, 50),
(2, 'USB Cable Type-C', 'Standard', 'USB001', 30, 30),
(2, 'HDMI Cable', '2.0', 'HDMI001', 25, 25),
(3, 'Network Cable Tester', 'NT-168', 'NCT001', 4, 4),
(3, 'Crimping Tool RJ45', 'Professional', 'CT001', 6, 6);

-- Assistance Types
INSERT INTO assistance_types (name, description, estimated_duration) VALUES
('Microsoft Office Setup', 'Installation and configuration of MS Office suite', 45),
('SPSS Installation', 'Statistical software installation and basic setup', 60),
('PC Troubleshooting', 'Hardware and software issue diagnosis and resolution', 30),
('Network Configuration', 'Network settings and connectivity issues', 45),
('Software Installation', 'General software installation assistance', 30),
('Hardware Diagnostics', 'Computer hardware testing and diagnostics', 60),
('Printer Setup', 'Printer installation and configuration', 30),
('Email Configuration', 'Email client setup and troubleshooting', 20);

-- Sample Admin User (password: admin123 - should be hashed in production)
INSERT INTO admins (username, email, password, full_name, role) VALUES
('admin', 'admin@university.edu', '$2y$10$iNsAYtaKrZTBKH8ExshmuOqTwHSd/WCTPr.A7KXYiPoHW9DgPR8.q', 'System Administrator', 'super_admin');

-- Sample Students (if students table doesn't exist)
INSERT INTO students (student_id, email, first_name, last_name, course, year_of_study) VALUES
('ST001', 'johndoe@university.edu', 'John', 'Doe', 'Computer Science', 2),
('ST002', 'janesmith@university.edu', 'Jane', 'Smith', 'Information Technology', 3),
('ST003', 'mikejohnson@university.edu', 'Mike', 'Johnson', 'Engineering', 1);