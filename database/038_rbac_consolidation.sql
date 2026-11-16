-- Flexihub Billing System
-- Feature 038: consolidate tenant RBAC on the access_* tables.
-- Creates the canonical access model and copies compatible legacy tenant role
-- definitions, module permissions and user assignments without deleting legacy data.
-- Review existing schema and backup before applying to a live database.

CREATE TABLE IF NOT EXISTS access_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_access_role_tenant_name (tenant_id, name),
    KEY idx_access_roles_tenant (tenant_id),
    CONSTRAINT fk_access_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_role_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(100) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_approve TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_access_role_module (role_id, module_key),
    KEY idx_access_role_permissions_tenant (tenant_id),
    CONSTRAINT fk_access_rp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_access_rp_role FOREIGN KEY (role_id) REFERENCES access_roles(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_user_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_access_user_role (user_id, role_id),
    KEY idx_access_user_roles_tenant (tenant_id),
    CONSTRAINT fk_access_ur_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_access_ur_role FOREIGN KEY (role_id) REFERENCES access_roles(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS flexihub_migrate_legacy_tenant_roles;
DELIMITER $$
CREATE PROCEDURE flexihub_migrate_legacy_tenant_roles()
BEGIN
    DECLARE v_roles INT DEFAULT 0;
    DECLARE v_role_perms INT DEFAULT 0;
    DECLARE v_user_roles INT DEFAULT 0;
    DECLARE v_users_tenant INT DEFAULT 0;

    SELECT COUNT(*) INTO v_roles
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles';

    SELECT COUNT(*) INTO v_role_perms
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions';

    SELECT COUNT(*) INTO v_user_roles
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_roles';

    SELECT COUNT(*) INTO v_users_tenant
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='tenant_id';

    IF v_roles > 0 THEN
        IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='tenant_id')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='name')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='description')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='is_system') THEN
            INSERT IGNORE INTO access_roles (tenant_id, name, description, is_system, status)
            SELECT r.tenant_id, r.name, r.description, COALESCE(r.is_system,0), 'active'
            FROM roles r
            INNER JOIN tenants t ON t.id=r.tenant_id
            WHERE r.name IS NOT NULL AND TRIM(r.name)<>'';
        END IF;
    END IF;

    IF v_role_perms > 0 AND v_roles > 0 THEN
        IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='role_id')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='id')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='module_key')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='can_view')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='can_create')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='can_edit')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='can_delete')
           AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND COLUMN_NAME='can_approve') THEN
            INSERT IGNORE INTO access_role_permissions
                (tenant_id, role_id, module_key, can_view, can_create, can_edit, can_delete, can_approve)
            SELECT r.tenant_id, ar.id, rp.module_key, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_approve
            FROM role_permissions rp
            INNER JOIN roles r ON r.id=rp.role_id
            INNER JOIN access_roles ar ON ar.tenant_id=r.tenant_id AND ar.name=r.name
            WHERE rp.module_key IS NOT NULL
            ;
        END IF;
    END IF;

    IF v_user_roles > 0 AND v_roles > 0 AND v_users_tenant > 0
       AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_roles' AND COLUMN_NAME='user_id')
       AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_roles' AND COLUMN_NAME='role_id')
       AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='id') THEN
        INSERT IGNORE INTO access_user_roles (tenant_id, user_id, role_id)
        SELECT u.tenant_id, ur.user_id, ar.id
        FROM user_roles ur
        INNER JOIN users u ON u.id=ur.user_id
        INNER JOIN roles r ON r.id=ur.role_id AND r.tenant_id=u.tenant_id
        INNER JOIN access_roles ar ON ar.tenant_id=u.tenant_id AND ar.name=r.name
        WHERE u.tenant_id IS NOT NULL AND u.tenant_id>0;
    END IF;
END$$
DELIMITER ;

CALL flexihub_migrate_legacy_tenant_roles();
DROP PROCEDURE IF EXISTS flexihub_migrate_legacy_tenant_roles();
