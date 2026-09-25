-- TASK-006 additive billing/tax snapshot migration. Never reset business data.
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS taxable_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER discount_total,
  ADD COLUMN IF NOT EXISTS cgst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER taxable_total,
  ADD COLUMN IF NOT EXISTS sgst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER cgst_total,
  ADD COLUMN IF NOT EXISTS igst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER sgst_total,
  ADD COLUMN IF NOT EXISTS rounding_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER gst_total,
  ADD COLUMN IF NOT EXISTS tax_policy_code VARCHAR(40) NULL AFTER rounding_adjustment,
  ADD COLUMN IF NOT EXISTS cancelled_by_ref VARCHAR(24) NULL AFTER cancel_reason,
  ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER cancelled_by_ref,
  ADD UNIQUE KEY uq_inv_order (franchise_ref, order_ref),
  ADD INDEX idx_inv_status (franchise_ref, status, invoice_date);

ALTER TABLE invoice_items
  ADD COLUMN IF NOT EXISTS taxable_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER discount,
  ADD COLUMN IF NOT EXISTS cgst_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER gst_percent,
  ADD COLUMN IF NOT EXISTS sgst_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER cgst_amount,
  ADD COLUMN IF NOT EXISTS igst_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER sgst_amount,
  ADD COLUMN IF NOT EXISTS total_tax DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER igst_amount;

INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive)
VALUES ('billing','cancel','Cancel Invoice',1), ('billing','generate','Generate Invoice',1);
