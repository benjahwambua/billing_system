-- Flexihub Billing System
-- Feature 042: secure customer self-service portal credentials and invitations.
-- Review portal URL, TLS and session-cookie configuration before enabling externally.

CREATE TABLE IF NOT EXISTS customer_portal_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'invited',
    invite_token_hash CHAR(64) NULL,
    invite_expires_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_portal_tenant_customer (tenant_id, customer_id),
    UNIQUE KEY uq_customer_portal_tenant_email (tenant_id, email),
    KEY idx_customer_portal_invite (tenant_id, invite_token_hash, invite_expires_at),
    KEY idx_customer_portal_status (tenant_id, status),
    CONSTRAINT fk_customer_portal_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
