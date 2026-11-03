-- Migration 004: Universal Access Control & Role Flow Evolution
-- Safe idempotent DDL and seed updates for UAMC spec

-- 1. Extend user role enum to support ACCOUNTANT and DISTRIBUTOR_EMPLOYEE
ALTER TABLE users 
  MODIFY COLUMN role ENUM(
    'SUPER_ADMIN',
    'FRANCHISE_ADMIN',
    'SALES',
    'ACCOUNTANT',
    'DISTRIBUTOR',
    'DISTRIBUTOR_EMPLOYEE'
  ) NOT NULL;

-- 2. Extend order status enum to support PENDING_APPROVAL and add distributor approval audit fields
ALTER TABLE orders
  MODIFY COLUMN status ENUM(
    'DRAFT',
    'PENDING_APPROVAL',
    'SUBMITTED',
    'CONFIRMED',
    'PROCESSING',
    'DISPATCHED',
    'DELIVERED',
    'CANCELLED'
  ) NOT NULL DEFAULT 'DRAFT',
  ADD COLUMN IF NOT EXISTS distributor_approved_by_ref VARCHAR(24) DEFAULT NULL AFTER sales_user_ref,
  ADD COLUMN IF NOT EXISTS distributor_approved_at DATETIME DEFAULT NULL AFTER distributor_approved_by_ref;

-- 3. Update oauth_clients: Allow DISTRIBUTOR_EMPLOYEE to authenticate via portal surface
UPDATE oauth_clients 
SET allowed_roles = 'DISTRIBUTOR,DISTRIBUTOR_EMPLOYEE' 
WHERE client_id = 'crm-portal';

-- Also allow ACCOUNTANT on admin surface if company user uses admin client
UPDATE oauth_clients 
SET allowed_roles = 'FRANCHISE_ADMIN,SALES,ACCOUNTANT' 
WHERE client_id = 'crm-admin';
