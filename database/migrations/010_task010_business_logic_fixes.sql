-- TASK-010: KYC Retention and Business Logic Fixes
-- Add deleted_at for KYC documents retention policy (soft delete upon rejection/archive)

ALTER TABLE kyc_documents ADD COLUMN deleted_at DATETIME NULL;
