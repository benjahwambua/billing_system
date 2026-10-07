-- Flexihub platform-level M-Pesa collection gateway for SaaS subscription payments.
CREATE TABLE IF NOT EXISTS platform_payment_gateways (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL DEFAULT 'mpesa',
    name VARCHAR(150) NOT NULL DEFAULT 'Flexihub M-Pesa',
    environment ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
    shortcode_type ENUM('paybill','till') NOT NULL DEFAULT 'paybill',
    shortcode VARCHAR(40) NOT NULL,
    consumer_key_encrypted TEXT NULL,
    consumer_secret_encrypted TEXT NULL,
    passkey_encrypted TEXT NULL,
    callback_token VARCHAR(128) NOT NULL,
    callback_url VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    is_default TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_gateway_provider (provider),
    KEY idx_platform_gateway_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
