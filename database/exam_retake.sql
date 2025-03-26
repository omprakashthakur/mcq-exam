-- Create exam retake requests table
CREATE TABLE IF NOT EXISTS exam_retake_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    exam_set_id INT NOT NULL,
    previous_attempt_id INT NOT NULL,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    reviewed_by INT,
    reviewed_at DATETIME,
    retake_count INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_set_id) REFERENCES exam_sets(id) ON DELETE CASCADE,
    FOREIGN KEY (previous_attempt_id) REFERENCES exam_attempts(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);