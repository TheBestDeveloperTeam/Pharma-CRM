-- TASK-008 additive financial integrity indexes and PDC metadata foundation.
ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS bank_name VARCHAR(120) NULL AFTER reference_no,
  ADD COLUMN IF NOT EXISTS cheque_number VARCHAR(80) NULL AFTER bank_name,
  ADD COLUMN IF NOT EXISTS cheque_date DATE NULL AFTER cheque_number,
  ADD COLUMN IF NOT EXISTS pdc_due_date DATE NULL AFTER cheque_date,
  ADD INDEX idx_pay_status (franchise_ref, status, payment_date);

ALTER TABLE payment_allocations
  ADD COLUMN IF NOT EXISTS reversed_at DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS reversed_by_ref VARCHAR(24) NULL AFTER reversed_at,
  ADD COLUMN IF NOT EXISTS reversal_reason VARCHAR(255) NULL AFTER reversed_by_ref;

INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive)
VALUES ('payments','allocate','Allocate Payment',1), ('payments','cancel','Cancel Payment',1), ('payments','pdc','PDC Operations',1);
