-- Flexihub Billing System
-- Phase 27: SmartISP feature-gap foundation
USE billing_system;

CREATE TABLE IF NOT EXISTS olt_devices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, vendor VARCHAR(80) NULL, model VARCHAR(120) NULL,
 host VARCHAR(255) NULL, api_port INT UNSIGNED NOT NULL DEFAULT 161, username VARCHAR(120) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'active', site_id BIGINT UNSIGNED NULL,
 description TEXT NULL, last_seen_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_olt_tenant_status(tenant_id,status), KEY idx_olt_site(tenant_id,site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onu_devices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 olt_id BIGINT UNSIGNED NULL, serial_number VARCHAR(120) NULL, name VARCHAR(150) NULL,
 pon_port VARCHAR(50) NULL, customer_id BIGINT UNSIGNED NULL, service_account_id BIGINT UNSIGNED NULL,
 ip_address VARCHAR(64) NULL, status VARCHAR(30) NOT NULL DEFAULT 'unknown',
 signal_rx VARCHAR(40) NULL, signal_tx VARCHAR(40) NULL, last_seen_at DATETIME NULL,
 notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_onu_serial_tenant(tenant_id,serial_number), KEY idx_onu_tenant_status(tenant_id,status),
 KEY idx_onu_customer(tenant_id,customer_id), KEY idx_onu_olt(tenant_id,olt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS network_map_nodes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 node_type VARCHAR(40) NOT NULL, node_id BIGINT UNSIGNED NULL, label VARCHAR(150) NOT NULL,
 latitude DECIMAL(10,7) NULL, longitude DECIMAL(10,7) NULL, metadata JSON NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_map_nodes_tenant(tenant_id), KEY idx_map_nodes_type(tenant_id,node_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS network_map_links (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 source_node_id BIGINT UNSIGNED NOT NULL, target_node_id BIGINT UNSIGNED NOT NULL,
 link_type VARCHAR(40) NOT NULL DEFAULT 'ethernet', status VARCHAR(30) NOT NULL DEFAULT 'active',
 bandwidth_mbps DECIMAL(12,2) NULL, metadata JSON NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_map_links_tenant(tenant_id), KEY idx_map_links_source(tenant_id,source_node_id),
 KEY idx_map_links_target(tenant_id,target_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotspot_vouchers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 code VARCHAR(100) NOT NULL, package_id BIGINT UNSIGNED NULL, batch_reference VARCHAR(100) NULL,
 price DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'unused',
 sold_at DATETIME NULL, activated_at DATETIME NULL, expires_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_voucher_code_tenant(tenant_id,code), KEY idx_voucher_tenant_status(tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_sales (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 agent_name VARCHAR(150) NOT NULL, agent_phone VARCHAR(40) NULL, product_type VARCHAR(50) NOT NULL,
 reference VARCHAR(120) NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 commission DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'completed',
 sold_at DATETIME DEFAULT CURRENT_TIMESTAMP, notes TEXT NULL,
 KEY idx_agent_sales_tenant_date(tenant_id,sold_at), KEY idx_agent_sales_agent(tenant_id,agent_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS adverts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL, message TEXT NOT NULL, image_url VARCHAR(500) NULL,
 target VARCHAR(50) NOT NULL DEFAULT 'all', status VARCHAR(30) NOT NULL DEFAULT 'draft',
 starts_at DATETIME NULL, ends_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_adverts_tenant_status(tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_chats (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL, channel VARCHAR(30) NOT NULL DEFAULT 'web',
 subject VARCHAR(200) NULL, status VARCHAR(30) NOT NULL DEFAULT 'open',
 assigned_user_id BIGINT UNSIGNED NULL, last_message_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_customer_chat_tenant_status(tenant_id,status), KEY idx_customer_chat_customer(tenant_id,customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_chat_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 chat_id BIGINT UNSIGNED NOT NULL, sender_type VARCHAR(30) NOT NULL, sender_id BIGINT UNSIGNED NULL,
 message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_chat_messages(tenant_id,chat_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_gateways (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, provider VARCHAR(80) NOT NULL, sender_id VARCHAR(80) NULL,
 endpoint_url VARCHAR(500) NULL, credentials_encrypted TEXT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'inactive', balance DECIMAL(14,2) NULL,
 last_verified_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_sms_gateway_tenant_status(tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS failed_mpesa_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 gateway_transaction_id BIGINT UNSIGNED NULL, phone_number VARCHAR(40) NULL,
 amount DECIMAL(14,2) NOT NULL DEFAULT 0, account_reference VARCHAR(120) NULL,
 failure_code VARCHAR(50) NULL, failure_reason VARCHAR(500) NULL,
 retry_status VARCHAR(30) NOT NULL DEFAULT 'pending', retry_count INT UNSIGNED NOT NULL DEFAULT 0,
 last_retry_at DATETIME NULL, resolved_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_failed_mpesa_tenant_status(tenant_id,retry_status), KEY idx_failed_mpesa_tx(tenant_id,gateway_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_theme_settings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 theme_name VARCHAR(80) NOT NULL DEFAULT 'Flexihub Blue', primary_color VARCHAR(20) NULL,
 accent_color VARCHAR(20) NULL, logo_url VARCHAR(500) NULL, dark_mode TINYINT(1) NOT NULL DEFAULT 1,
 custom_css TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_theme_tenant(tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
