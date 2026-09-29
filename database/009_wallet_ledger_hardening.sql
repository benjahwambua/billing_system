-- Flexihub Billing System
-- Phase 9: Tenant wallet ledger hardening
-- Wallets are tenant-owned and remain separate from ISP customer collections.
-- M-Pesa and other gateways use the generic source/payment/reference fields.

ALTER TABLE tenant_wallets
    MODIFY balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    MODIFY currency VARCHAR(10) NOT NULL DEFAULT 'KES';

ALTER TABLE wallet_transactions
    ADD COLUMN direction VARCHAR(10) NOT NULL DEFAULT 'credit' AFTER transaction_type,
    ADD COLUMN balance_before DECIMAL(14,2) NULL AFTER amount,
    ADD COLUMN balance_after DECIMAL(14,2) NULL AFTER balance_before,
    ADD COLUMN source VARCHAR(40) NULL AFTER description,
    ADD COLUMN payment_method VARCHAR(30) NULL AFTER source,
    ADD COLUMN external_reference VARCHAR(150) NULL AFTER payment_method,
    ADD COLUMN gateway_transaction_id VARCHAR(150) NULL AFTER external_reference,
    ADD COLUMN idempotency_key VARCHAR(150) NULL AFTER gateway_transaction_id,
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER idempotency_key;

ALTER TABLE wallet_transactions
    ADD KEY idx_wallet_tx_tenant_created (tenant_id, created_at),
    ADD KEY idx_wallet_tx_external_ref (tenant_id, external_reference),
    ADD KEY idx_wallet_tx_gateway (tenant_id, gateway_transaction_id),
    ADD UNIQUE KEY uq_wallet_tx_idempotency (tenant_id, idempotency_key);

ALTER TABLE wallet_transactions
    ADD CONSTRAINT fk_wallet_tx_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
