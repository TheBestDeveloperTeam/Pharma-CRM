-- TASK-007 additive dispatch/delivery history and delivery metadata.
ALTER TABLE dispatches
  ADD COLUMN delivered_at DATETIME NULL AFTER dispatch_date,
  ADD COLUMN delivery_remarks VARCHAR(255) NULL AFTER remarks,
  ADD INDEX idx_dsp_status (franchise_ref, status, dispatch_date);

CREATE TABLE IF NOT EXISTS dispatch_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  dispatch_ref VARCHAR(24) NOT NULL,
  from_status VARCHAR(24) NULL,
  to_status VARCHAR(24) NOT NULL,
  actor_ref VARCHAR(24) NOT NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dsh_dispatch (franchise_ref, dispatch_ref, created_at),
  CONSTRAINT fk_dsh_dispatch FOREIGN KEY (franchise_ref, dispatch_ref) REFERENCES dispatches(franchise_ref, dispatch_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive)
VALUES ('dispatch','confirm','Confirm Delivery',1);
