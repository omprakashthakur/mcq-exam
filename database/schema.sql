CREATE DATABASE IF NOT EXISTS mcq_exam_db;
USE mcq_exam_db;

-- Users table with is_active column
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    verification_token VARCHAR(64),
    is_verified TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Admin profiles table
CREATE TABLE IF NOT EXISTS admin_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    is_super_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default super admin account if it doesn't exist
INSERT INTO users (username, email, password, role, is_active, is_verified) 
VALUES ('admin', 'admin@mcqexam.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1)
ON DUPLICATE KEY UPDATE id=id;

-- Get the admin user ID
SET @admin_id = (SELECT id FROM users WHERE username = 'admin');

-- Insert admin profile for the default admin
INSERT INTO admin_profiles (user_id, full_name, is_super_admin)
VALUES (@admin_id, 'System Administrator', 1)
ON DUPLICATE KEY UPDATE user_id=user_id;

-- Student profiles table
CREATE TABLE IF NOT EXISTS student_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Exam sets table
CREATE TABLE IF NOT EXISTS exam_sets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL DEFAULT 60,
    pass_percentage DECIMAL(5,2) NOT NULL DEFAULT 70.00,
    is_active TINYINT(1) DEFAULT 1,
    is_public TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Add exam_set_code column to exam_sets table if it doesn't exist
ALTER TABLE exam_sets
ADD COLUMN IF NOT EXISTS exam_set_code VARCHAR(20) UNIQUE DEFAULT NULL;

-- Create index on exam_set_code for faster lookups
CREATE INDEX IF NOT EXISTS idx_exam_set_code ON exam_sets(exam_set_code);

-- Questions table
CREATE TABLE IF NOT EXISTS questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_set_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_answer ENUM('A','B','C','D') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_set_id) REFERENCES exam_sets(id)
);

-- Add is_multiple_answer column to questions table
ALTER TABLE questions ADD COLUMN IF NOT EXISTS is_multiple_answer TINYINT(1) DEFAULT 0;

-- Update questions table for multiple answers
ALTER TABLE questions
ADD COLUMN IF NOT EXISTS max_correct_answers INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS correct_explanation TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS incorrect_explanation TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS option_e TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS option_f TEXT DEFAULT NULL;

-- Create correct_answers table for multiple answers
CREATE TABLE IF NOT EXISTS correct_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    correct_option CHAR(1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_question_option (question_id, correct_option)
);

-- Update existing correct_answers table if needed
ALTER TABLE correct_answers 
MODIFY COLUMN correct_option CHAR(1) NOT NULL CHECK (correct_option IN ('A','B','C','D','E','F'));

-- Exam requests table
CREATE TABLE IF NOT EXISTS exam_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    exam_set_id INT NOT NULL,
    request_reason TEXT,
    preferred_date DATE NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    response_date DATETIME,
    reviewed_by INT,
    remarks TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exam_set_id) REFERENCES exam_sets(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Exam access table
CREATE TABLE IF NOT EXISTS exam_access (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_set_id INT NOT NULL,
    user_id INT NOT NULL,
    access_code VARCHAR(10) NOT NULL,
    access_url VARCHAR(32) NOT NULL,
    expiry_date DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_set_id) REFERENCES exam_sets(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Exam attempts table
CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    exam_set_id INT NOT NULL,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME,
    status ENUM('in_progress', 'completed') DEFAULT 'in_progress',
    score DECIMAL(5,2) DEFAULT 0.00,
    total_questions INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    incorrect_answers INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exam_set_id) REFERENCES exam_sets(id)
);

-- Update exam_attempts table structure first
ALTER TABLE exam_attempts
ADD COLUMN IF NOT EXISTS total_questions INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS correct_answers INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS incorrect_answers INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS status ENUM('in_progress', 'completed') DEFAULT 'in_progress',
MODIFY COLUMN score DECIMAL(5,2) DEFAULT 0.00;

-- Add unanswered_questions column to exam_attempts if it doesn't exist
ALTER TABLE exam_attempts 
ADD COLUMN IF NOT EXISTS unanswered_questions INT DEFAULT 0;

-- Add or modify exam_attempts table columns
ALTER TABLE exam_attempts 
ADD COLUMN IF NOT EXISTS total_questions INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS correct_answers INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS incorrect_answers INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS unanswered_questions INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS time_taken INT DEFAULT 0,
MODIFY COLUMN score DECIMAL(5,2) DEFAULT 0.00,
MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending';

-- User answers table
CREATE TABLE IF NOT EXISTS user_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A','B','C','D') NOT NULL,
    selected_options VARCHAR(255) DEFAULT NULL,
    is_correct TINYINT(1) NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_attempt_id) REFERENCES exam_attempts(id),
    FOREIGN KEY (question_id) REFERENCES questions(id),
    UNIQUE KEY unique_answer (exam_attempt_id, question_id)
);

-- Update user_answers table
ALTER TABLE user_answers
ADD COLUMN IF NOT EXISTS selected_options VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_correct TINYINT(1) DEFAULT 0;

-- Add selected_options column to user_answers if it doesn't exist
ALTER TABLE user_answers 
ADD COLUMN IF NOT EXISTS selected_options VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_correct TINYINT(1) DEFAULT 0;

-- Create admin settings table
CREATE TABLE IF NOT EXISTS admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
);

-- Create admin activity log table
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type ENUM('create', 'update', 'delete', 'approve', 'reject', 'share', 'backup', 'restore') NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create exam categories table
CREATE TABLE IF NOT EXISTS exam_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create exam templates table
CREATE TABLE IF NOT EXISTS exam_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL,
    pass_percentage DECIMAL(5,2) NOT NULL,
    category_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES exam_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create exam template questions table
CREATE TABLE IF NOT EXISTS template_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    explanation TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES exam_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create default admin settings
INSERT IGNORE INTO admin_settings (setting_key, setting_value) VALUES
('site_name', 'MCQ Exam System'),
('allow_registration', '1'),
('verify_email', '1'),
('max_attempts_per_exam', '3'),
('default_exam_duration', '60'),
('minimum_pass_percentage', '60'),
('maintenance_mode', '0'),
('notification_email', 'admin@mcqexam.com');

-- Create sample exam category
INSERT IGNORE INTO exam_categories (name, description) VALUES
('General', 'General purpose exams'),
('Technical', 'Technical and programming related exams'),
('Academic', 'Academic subject exams');