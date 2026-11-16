-- Flexihub Billing System
-- Feature 043: tenant-scoped customer support tickets and message history.
-- Customer portal users and tenant staff share the same ticket thread.

CREATE TABLE IF NOT EXISTS customer_support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    portal_user_id BIGINT UNSIGNED NULL,
    ticket_number VARCHAR(50) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'technical',
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    assigned_user_id BIGINT UNSIGNED NULL,
    last_message_at DATETIME NULL,
    resolved_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_ticket_tenant_number (tenant_id, ticket_number),
    KEY idx_customer_tickets_tenant_status (tenant_id, status, priority),
    KEY idx_customer_tickets_customer (tenant_id, customer_id, created_at),
    KEY idx_customer_tickets_assignee (tenant_id, assigned_user_id, status),
    CONSTRAINT fk_customer_support_tickets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_support_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    sender_id BIGINT UNSIGNED NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_support_messages_ticket (tenant_id, ticket_id, id),
    CONSTRAINT fk_customer_support_messages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
