-- Flexihub Billing System
-- Phase 22: Notification queue and automated billing notices
-- Run after 021_overdue_suspension.sql.

USE billing_system;

CREATE TABLE IF NOT EXISTS notification_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'sms',
    subject VARCHAR(255) NULL,
    body TEXT NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_template (tenant_id,event_type,channel),
    KEY idx_notification_templates_tenant (tenant_id,status),
    CONSTRAINT fk_notification_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notification_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    invoice_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'sms',
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NULL,
    sent_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notification_queue_worker (tenant_id,status,available_at),
    KEY idx_notification_queue_customer (tenant_id,customer_id),
    KEY idx_notification_queue_invoice (tenant_id,invoice_id),
    CONSTRAINT fk_notification_queue_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_invoices_customer_due
    ON invoices (tenant_id, customer_id, due_date, status);
