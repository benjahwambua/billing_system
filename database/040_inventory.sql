-- Flexihub Billing System
-- Feature 040: tenant-scoped inventory catalogue, locations and stock ledger.
-- Back up the database and review tenant key compatibility before applying.

CREATE TABLE IF NOT EXISTS inventory_locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    location_type VARCHAR(30) NOT NULL DEFAULT 'store',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_location_tenant_name (tenant_id, name),
    UNIQUE KEY uq_inventory_location_tenant_id (tenant_id, id),
    KEY idx_inventory_locations_tenant_status (tenant_id, status),
    CONSTRAINT fk_inventory_locations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(80) NOT NULL,
    name VARCHAR(180) NOT NULL,
    category VARCHAR(100) NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
    description TEXT NULL,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(14,3) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_item_tenant_sku (tenant_id, sku),
    UNIQUE KEY uq_inventory_item_tenant_id (tenant_id, id),
    KEY idx_inventory_items_tenant_status (tenant_id, status),
    KEY idx_inventory_items_tenant_category (tenant_id, category),
    CONSTRAINT fk_inventory_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    movement_type VARCHAR(30) NOT NULL,
    quantity_delta DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    reference VARCHAR(120) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_movements_item (tenant_id, item_id, location_id, created_at),
    KEY idx_inventory_movements_location (tenant_id, location_id, created_at),
    CONSTRAINT fk_inventory_movements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movements_item FOREIGN KEY (tenant_id, item_id) REFERENCES inventory_items(tenant_id, id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movements_location FOREIGN KEY (tenant_id, location_id) REFERENCES inventory_locations(tenant_id, id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
