-- Add created_by column to exam_access table
ALTER TABLE exam_access 
ADD COLUMN IF NOT EXISTS created_by INT,
ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;