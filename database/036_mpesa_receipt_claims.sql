-- Flexihub Billing System
-- Phase 36: globally unique M-Pesa receipt claims.
-- PRECONDITION: run database/mpesa_receipt_integrity_preflight.sql and resolve every duplicate before applying.
-- This migration intentionally fails on duplicate historical receipts; do not suppress those conflicts.
-- Apply once, after migrations 013 and 025/026 have established the source tables.

CREATE TABLE IF NOT EXISTS mpesa_receipt_claims (
    receipt VARCHAR(100) NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_transaction_id BIGINT UNSIGNED NOT NULL,
    claimed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (receipt),
    KEY idx_mpesa_receipt_claim_tenant (tenant_id, claimed_at),
    KEY idx_mpesa_receipt_claim_source (source_type, source_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill known receipts so the database constraint also protects against historical reuse.
-- If any receipt is already duplicated across these sources, this INSERT fails and the
-- duplicate must be reconciled before retrying the migration.
INSERT INTO mpesa_receipt_claims (receipt, tenant_id, source_type, source_transaction_id)
SELECT receipt, tenant_id, source_type, source_transaction_id
FROM (
    SELECT TRIM(provider_receipt) AS receipt, tenant_id,
           'gateway_transaction' AS source_type, id AS source_transaction_id
    FROM payment_gateway_transactions
    WHERE provider = 'mpesa' AND provider_receipt IS NOT NULL
      AND TRIM(provider_receipt) <> ''

    UNION ALL

    SELECT TRIM(external_transaction_id) AS receipt, tenant_id,
           'platform_payment' AS source_type, id AS source_transaction_id
    FROM platform_payments
    WHERE provider = 'mpesa' AND external_transaction_id IS NOT NULL
      AND TRIM(external_transaction_id) <> ''
) AS existing_receipts
WHERE CHAR_LENGTH(receipt) <= 100;
