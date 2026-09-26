-- TASK-004 additive migration. Do not reset or drop existing business data.
ALTER TABLE parties ADD COLUMN whatsapp VARCHAR(20) NULL;
ALTER TABLE parties ADD COLUMN party_type VARCHAR(80) NULL;
ALTER TABLE parties ADD COLUMN drug_license_validity DATE NULL;
ALTER TABLE parties ADD COLUMN area VARCHAR(120) NULL;
ALTER TABLE parties ADD COLUMN remarks VARCHAR(500) NULL;

CREATE TABLE IF NOT EXISTS party_product_interests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  party_ref VARCHAR(24) NOT NULL,
  product_ref VARCHAR(24) NOT NULL,
  created_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_party_product_interest (franchise_ref, party_ref, product_ref),
  INDEX idx_party_interest (franchise_ref, party_ref),
  CONSTRAINT fk_ppi_party FOREIGN KEY (franchise_ref, party_ref) REFERENCES parties(franchise_ref, party_ref),
  CONSTRAINT fk_ppi_product FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
