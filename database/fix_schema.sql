-- Add is_active column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

-- Add created_by column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_by INT;
ALTER TABLE users ADD FOREIGN KEY IF NOT EXISTS (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add admin_profiles table if it doesn't exist
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

-- Create default admin if not exists
INSERT IGNORE INTO users (username, email, password, role, is_active, is_verified) 
VALUES ('admin', 'admin@mcqexam.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1);

-- Get admin ID
SET @admin_id = (SELECT id FROM users WHERE username = 'admin');

-- Create admin profile if not exists
INSERT IGNORE INTO admin_profiles (user_id, full_name, is_super_admin)
VALUES (@admin_id, 'System Administrator', 1);

-- Create exam_assignments table if not exists
CREATE TABLE IF NOT EXISTS exam_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    exam_set_id INT NOT NULL,
    assigned_by INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NULL,
    status ENUM('pending', 'in_progress', 'completed', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_set_id) REFERENCES exam_sets(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_assignment (student_id, exam_set_id, status)
);