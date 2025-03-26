-- Drop old column and fix structure
ALTER TABLE user_answers DROP COLUMN IF EXISTS selected_answer;
ALTER TABLE user_answers MODIFY selected_options VARCHAR(255) NOT NULL;
ALTER TABLE user_answers MODIFY is_correct TINYINT(1) NOT NULL DEFAULT 0;

-- Add UNIQUE constraint to prevent duplicate answers
ALTER TABLE user_answers 
DROP INDEX IF EXISTS unique_answer,
ADD UNIQUE INDEX unique_answer (exam_attempt_id, question_id);