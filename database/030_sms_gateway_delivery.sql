USE billing_system;

CREATE TABLE IF NOT EXISTS sms_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 gateway_id BIGINT UNSIGNED NULL,
 recipient VARCHAR(40) NOT NULL,
 message TEXT NOT NULL,
 sender_id VARCHAR(80) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'queued',
 provider_message_id VARCHAR(150) NULL,
 response_body TEXT NULL,
 failure_reason VARCHAR(500) NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 sent_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_sms_messages_tenant_status(tenant_id,status),
 KEY idx_sms_messages_tenant_created(tenant_id,created_at),
 KEY idx_sms_messages_provider(tenant_id,provider_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE sms_gateways ADD COLUMN IF NOT EXISTS api_method VARCHAR(10) NOT NULL DEFAULT 'POST';
ALTER TABLE sms_gateways ADD COLUMN IF NOT EXISTS auth_type VARCHAR(30) NOT NULL DEFAULT 'bearer';
ALTER TABLE sms_gateways ADD COLUMN IF NOT EXISTS request_template TEXT NULL;
ALTER TABLE sms_gateways ADD COLUMN IF NOT EXISTS balance_endpoint VARCHAR(500) NULL;
ALTER TABLE sms_gateways ADD COLUMN IF NOT EXISTS last_error VARCHAR(500) NULL;