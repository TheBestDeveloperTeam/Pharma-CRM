-- TASK-005 additive migration. Do not reset or drop business data.
ALTER TABLE orders
  MODIFY COLUMN status ENUM('DRAFT','SUBMITTED','CONFIRMED','PROCESSING','DISPATCHED','DELIVERED','CANCELLED') NOT NULL DEFAULT 'DRAFT';
ALTER TABLE orders MODIFY COLUMN territory_status ENUM('OK','OVERRIDDEN','BLOCKED','UNASSIGNED','CONFLICT') NOT NULL DEFAULT 'OK';
ALTER TABLE orders ADD COLUMN billing_address TEXT NULL;
ALTER TABLE orders ADD COLUMN pricing_tier_ref VARCHAR(24) NULL;

INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive)
VALUES ('orders','confirm','Confirm',0),('inventory','reserve','Reserve Stock',1),('inventory','release','Release Reservation',1);
