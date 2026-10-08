USE billing_system;
ALTER TABLE failed_mpesa_transactions
 ADD COLUMN IF NOT EXISTS resolution_note VARCHAR(500) NULL,
 ADD COLUMN IF NOT EXISTS resolved_by BIGINT UNSIGNED NULL;
