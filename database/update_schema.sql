-- Add is_retake column to exam_access table
ALTER TABLE exam_access
ADD COLUMN is_retake TINYINT(1) DEFAULT 0;