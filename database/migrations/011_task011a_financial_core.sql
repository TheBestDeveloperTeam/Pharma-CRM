-- TASK-011A: additive financial history and authoritative GST/PDC support.
ALTER TABLE franchises ADD COLUMN IF NOT EXISTS state_ref VARCHAR(24) NULL AFTER address;
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS supplier_state_ref VARCHAR(24) NULL AFTER due_date,
  ADD COLUMN IF NOT EXISTS place_of_supply_state_ref VARCHAR(24) NULL AFTER supplier_state_ref,
  ADD INDEX idx_inv_due_open (franchise_ref, status, due_date);
ALTER TABLE invoice_items
  ADD COLUMN IF NOT EXISTS cgst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER gst_percent,
  ADD COLUMN IF NOT EXISTS sgst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER cgst_percent,
  ADD COLUMN IF NOT EXISTS igst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER sgst_percent;
CREATE TABLE IF NOT EXISTS payment_reversals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reversal_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  payment_ref VARCHAR(24) NOT NULL, reason VARCHAR(255) NOT NULL, idempotency_key VARCHAR(160) NOT NULL,
  reversed_by_ref VARCHAR(24) NOT NULL, reversed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pr_ref (franchise_ref,reversal_ref), UNIQUE KEY uq_pr_payment (franchise_ref,payment_ref),
  UNIQUE KEY uq_pr_idem (franchise_ref,idempotency_key), INDEX idx_pr_payment (franchise_ref,payment_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pdcs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pdc_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL, party_ref VARCHAR(24) NOT NULL,
  amount DECIMAL(18,2) NOT NULL, cheque_number VARCHAR(80) NULL, bank_name VARCHAR(120) NULL, due_date DATE NULL,
  status ENUM('PENDING','REALIZED','BOUNCED','CANCELLED') NOT NULL DEFAULT 'PENDING', payment_ref VARCHAR(24) NULL,
  bounce_reason VARCHAR(255) NULL, created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  realized_by_ref VARCHAR(24) NULL, realized_at DATETIME NULL, bounced_by_ref VARCHAR(24) NULL, bounced_at DATETIME NULL,
  UNIQUE KEY uq_pdc_ref (franchise_ref,pdc_ref), UNIQUE KEY uq_pdc_payment (franchise_ref,payment_ref), INDEX idx_pdc_party (franchise_ref,party_ref,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE pdcs ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL AFTER bounce_reason,
  ADD COLUMN IF NOT EXISTS cancelled_by_ref VARCHAR(24) NULL AFTER bounced_by_ref,
  ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER bounced_at;
INSERT IGNORE INTO auth_permission_catalogue (module_key,action_key,label,is_sensitive) VALUES
 ('payments','reverse','Reverse posted payment',1),('payments','pdc','Operate PDC lifecycle',1),('billing','outstanding','View outstanding and ageing',1);
