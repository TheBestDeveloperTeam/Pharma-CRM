-- TASK-003 additive migration. Does not reset or delete existing data.

ALTER TABLE products ADD COLUMN description VARCHAR(500) NULL;
ALTER TABLE products ADD COLUMN availability ENUM('IN_STOCK','LOW_STOCK','OUT_OF_STOCK') NOT NULL DEFAULT 'IN_STOCK';

ALTER TABLE product_prices ADD COLUMN mrp DECIMAL(18,2) NOT NULL DEFAULT 0.00;
ALTER TABLE product_prices ADD COLUMN pts DECIMAL(18,2) NOT NULL DEFAULT 0.00;
ALTER TABLE product_prices ADD COLUMN net_rate DECIMAL(18,2) NOT NULL DEFAULT 0.00;
ALTER TABLE product_prices ADD COLUMN override_reason VARCHAR(500) NULL;
ALTER TABLE product_prices ADD COLUMN updated_by_ref VARCHAR(24) NULL;
ALTER TABLE product_prices ADD COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE schemes ADD COLUMN scheme_type VARCHAR(80) NULL;
ALTER TABLE schemes ADD COLUMN updated_by_ref VARCHAR(24) NULL;
ALTER TABLE schemes ADD COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS catalog_master_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  master_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  master_key VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  description VARCHAR(500) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL,
  updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_catalog_master_ref (franchise_ref, master_ref),
  UNIQUE KEY uq_catalog_master_name (franchise_ref, master_key, name),
  INDEX idx_catalog_master_list (franchise_ref, master_key, status, name),
  CONSTRAINT fk_catalog_master_org FOREIGN KEY (org_ref) REFERENCES organizations(org_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
