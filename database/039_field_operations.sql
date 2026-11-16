-- Flexihub Billing System
-- Feature 039: tenant-scoped field operations and technician job tracking.
-- Back up the database and review the existing staffs/customers tenant keys before applying.

CREATE TABLE IF NOT EXISTS field_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    job_number VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    internet_account_id BIGINT UNSIGNED NULL,
    job_type VARCHAR(30) NOT NULL DEFAULT 'installation',
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    scheduled_at DATETIME NULL,
    assigned_staff_id BIGINT UNSIGNED NULL,
    service_address VARCHAR(500) NULL,
    resolution_notes TEXT NULL,
    customer_signoff_name VARCHAR(150) NULL,
    customer_signoff_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_field_jobs_tenant_number (tenant_id, job_number),
    KEY idx_field_jobs_tenant_status (tenant_id, status, priority),
    KEY idx_field_jobs_assignee (tenant_id, assigned_staff_id, status),
    KEY idx_field_jobs_customer (tenant_id, customer_id, created_at),
    KEY idx_field_jobs_schedule (tenant_id, scheduled_at),
    CONSTRAINT fk_field_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS field_job_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    assigned_staff_id BIGINT UNSIGNED NULL,
    update_type VARCHAR(30) NOT NULL DEFAULT 'note',
    status_from VARCHAR(30) NULL,
    status_to VARCHAR(30) NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_field_job_updates_job (tenant_id, job_id, id),
    KEY idx_field_job_updates_staff (tenant_id, assigned_staff_id, created_at),
    CONSTRAINT fk_field_job_updates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS field_job_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    storage_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_field_job_attachments_job (tenant_id, job_id, id),
    CONSTRAINT fk_field_job_attachments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
