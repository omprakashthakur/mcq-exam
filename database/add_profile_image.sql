-- Add profile_image column to admin_profiles if it doesn't exist
ALTER TABLE admin_profiles
ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL;

-- Add profile_image column to student_profiles if it doesn't exist
ALTER TABLE student_profiles
ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL;