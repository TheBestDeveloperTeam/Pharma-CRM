SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. OAuth clients for the 4 surfaces
INSERT INTO oauth_clients (client_id, surface, allowed_roles, status) VALUES
('crm-super',  'super',  'SUPER_ADMIN',     'ACTIVE'),
('crm-admin',  'admin',  'FRANCHISE_ADMIN', 'ACTIVE'),
('crm-sales',  'sales',  'SALES',           'ACTIVE'),
('crm-portal', 'portal', 'DISTRIBUTOR',     'ACTIVE')
ON DUPLICATE KEY UPDATE status = 'ACTIVE';

-- 2. Master Platform Organization
INSERT INTO organizations (org_ref, org_code, org_name, status, created_by_ref) VALUES
('ORG-PLATFORM0000000001', 'ACME', 'Acme Healthcare Group', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE org_name = 'Acme Healthcare Group';

-- 3. Mumbai Franchise
INSERT INTO franchises (franchise_ref, org_ref, franchise_code, franchise_name, status, created_by_ref) VALUES
('FRN-MUMBAI000000000001', 'ORG-PLATFORM0000000001', 'MUMBAI', 'Acme Mumbai PCD Franchise', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE franchise_name = 'Acme Mumbai PCD Franchise';

-- 4. Four Core Users (Password is 'Password@123')
INSERT INTO users (user_ref, org_ref, franchise_ref, role, full_name, email, password_hash, status, created_by_ref) VALUES
('USR-SUPERADMIN0000001', 'ORG-PLATFORM0000000001', NULL, 'SUPER_ADMIN', 'Super Admin', 'super@pharmacrm.local', '$argon2id$v=19$m=65536,t=4,p=1$MXdBclpqdGM2bzNGTU1Dbw$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('USR-FRNADMIN000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'FRANCHISE_ADMIN', 'Mumbai Admin', 'admin@pharmacrm.local', '$argon2id$v=19$m=65536,t=4,p=1$MXdBclpqdGM2bzNGTU1Dbw$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('USR-SALESREP000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SALES', 'Rajesh Sales', 'sales@pharmacrm.local', '$argon2id$v=19$m=65536,t=4,p=1$MXdBclpqdGM2bzNGTU1Dbw$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-FRNADMIN000000001'),
('USR-DISTRIBUTOR000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DISTRIBUTOR', 'Apollo Distributor', 'portal@pharmacrm.local', '$argon2id$v=19$m=65536,t=4,p=1$MXdBclpqdGM2bzNGTU1Dbw$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE status = 'ACTIVE';

SET FOREIGN_KEY_CHECKS = 1;
