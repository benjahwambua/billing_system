-- Link the existing tenant payment gateway transaction engine to platform SaaS invoices.
ALTER TABLE payment_gateway_transactions
    ADD COLUMN platform_invoice_id BIGINT UNSIGNED NULL AFTER hotspot_sale_id,
    ADD KEY idx_pgt_platform_invoice (platform_invoice_id);
