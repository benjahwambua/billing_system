-- Platform SaaS M-Pesa STK lifecycle fields.
-- Uses platform_payments as the platform transaction ledger so initiated,
-- pending, completed, failed and reversed states remain auditable.
ALTER TABLE platform_payments
    ADD COLUMN gateway_id BIGINT UNSIGNED NULL AFTER invoice_id,
    ADD COLUMN phone_number VARCHAR(30) NULL AFTER amount,
    ADD COLUMN idempotency_key VARCHAR(180) NULL AFTER external_transaction_id,
    ADD COLUMN merchant_request_id VARCHAR(150) NULL AFTER idempotency_key,
    ADD COLUMN checkout_request_id VARCHAR(150) NULL AFTER merchant_request_id,
    ADD COLUMN result_code VARCHAR(30) NULL AFTER checkout_request_id,
    ADD COLUMN result_description VARCHAR(255) NULL AFTER result_code,
    ADD COLUMN request_payload JSON NULL AFTER result_description,
    ADD COLUMN callback_payload JSON NULL AFTER request_payload,
    ADD COLUMN failure_reason VARCHAR(500) NULL AFTER callback_payload,
    ADD COLUMN expires_at DATETIME NULL AFTER failure_reason,
    ADD COLUMN confirmed_at DATETIME NULL AFTER expires_at,
    ADD UNIQUE KEY uq_platform_payment_idempotency (idempotency_key),
    ADD UNIQUE KEY uq_platform_payment_checkout (checkout_request_id),
    ADD KEY idx_platform_payment_gateway (gateway_id),
    ADD KEY idx_platform_payment_status_expiry (status,expires_at);
