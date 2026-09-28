-- 013_add_updated_at_to_job_queue.sql
-- Add missing updated_at column to job_queue table

ALTER TABLE job_queue
  ADD COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
