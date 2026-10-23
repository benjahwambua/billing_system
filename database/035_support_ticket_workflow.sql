-- Flexihub Billing System
-- Phase 035: tenant-scoped support tickets and conversation history.
-- Back up the database and check the schema before applying.

CREATE TABLE IF NOT EXISTS support_tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(200) NOT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'general',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  status VARCHAR(30) NOT NULL DEFAULT 'open',
  assigned_to BIGINT UNSIGNED NULL,
  resolution TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_support_tickets_tenant_status (tenant_id, status),
  KEY idx_support_tickets_tenant_updated (tenant_id, updated_at),
  KEY idx_support_tickets_assignee_status (assigned_to, status),
  KEY idx_support_tickets_created_by (tenant_id, created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  sender_type VARCHAR(20) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_support_messages_ticket (tenant_id, ticket_id, id),
  KEY idx_support_messages_sender (tenant_id, sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
