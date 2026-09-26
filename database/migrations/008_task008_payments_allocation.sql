-- TASK-008 additive financial integrity indexes and PDC metadata foundation.
ALTER TABLE payments
  ADD COLUMN bank_name VARCHAR(120) NULL AFTER reference_no,
  ADD COLUMN cheque_number VARCHAR(80) NULL AFTER bank_name,
  ADD COLUMN cheque_date DATE NULL AFTER cheque_number,
  ADD COLUMN pdc_due_date DATE NULL AFTER cheque_date,
  ADD INDEX idx_pay_status (franchise_ref, status, payment_date);

ALTER TABLE payment_allocations
  ADD COLUMN reversed_at DATETIME NULL AFTER status,
  ADD COLUMN reversed_by_ref VARCHAR(24) NULL AFTER reversed_at,
  ADD COLUMN reversal_reason VARCHAR(255) NULL AFTER reversed_by_ref;

INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive)
VALUES ('payments','allocate','Allocate Payment',1), ('payments','cancel','Cancel Payment',1), ('payments','pdc','PDC Operations',1);
