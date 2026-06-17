-- ============================================================
-- RUMS — Rental Unit Management System
-- Complete consolidated schema (base + all migrations merged)
-- Column names match PHP services exactly.
-- Generated: 2026-06-15
--
-- Usage:
--   mysql -u rdrdxkhd_rums -p rdrdxkhd_rums < rums_complete.sql
--
-- Idempotent: all indexes are defined INSIDE CREATE TABLE so
-- CREATE TABLE IF NOT EXISTS skips already-existing tables
-- (and their indexes) cleanly. Safe to re-run on a fresh DB.
-- ============================================================

CREATE DATABASE IF NOT EXISTS rdrdxkhd_rums
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE rdrdxkhd_rums;

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = '';

-- ============================================================
-- USERS
-- All 8 roles included from the start.
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    phone         VARCHAR(20)   NOT NULL,
    password      VARCHAR(255)  NOT NULL,
    role          ENUM('admin','manager','landlord','tenant',
                       'accountant','maintenance','auditor','security')
                  NOT NULL DEFAULT 'tenant',
    avatar        VARCHAR(255)  DEFAULT NULL,
    status        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    last_login    DATETIME      DEFAULT NULL,
    reset_token   VARCHAR(100)  DEFAULT NULL,
    reset_expires DATETIME      DEFAULT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user  (password: Admin@1234)
INSERT IGNORE INTO users (name, email, phone, password, role) VALUES
('System Admin', 'admin@rums.co.ke', '0700000000',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================================
-- LANDLORDS
-- ============================================================
CREATE TABLE IF NOT EXISTS landlords (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    id_number       VARCHAR(20)  NOT NULL UNIQUE,
    kra_pin         VARCHAR(20)  DEFAULT NULL,
    bank_name       VARCHAR(100) DEFAULT NULL,
    bank_account    VARCHAR(50)  DEFAULT NULL,
    bank_branch     VARCHAR(100) DEFAULT NULL,
    mpesa_number    VARCHAR(20)  DEFAULT NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    notes           TEXT         DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROPERTIES
-- Uses address_line1/2, address_city, address_county, address_country,
-- amenities, year_built — matching PropertyService::create/update.
-- address VIRTUAL column provides backward-compatible p.address search.
-- ============================================================
CREATE TABLE IF NOT EXISTS properties (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(200)  NOT NULL,
    property_type    ENUM('residential','commercial','mixed') NOT NULL DEFAULT 'residential',
    landlord_id      INT UNSIGNED  NOT NULL,
    manager_id       INT UNSIGNED  DEFAULT NULL,
    address_line1    VARCHAR(255)  DEFAULT NULL,
    address_line2    VARCHAR(255)  DEFAULT NULL,
    address_city     VARCHAR(100)  DEFAULT NULL,
    address_county   VARCHAR(100)  NOT NULL DEFAULT '',
    address_country  VARCHAR(100)  NOT NULL DEFAULT 'Kenya',
    description      TEXT          DEFAULT NULL,
    amenities        TEXT          DEFAULT NULL,
    image            VARCHAR(255)  DEFAULT NULL,
    total_units      INT           NOT NULL DEFAULT 0,
    year_built       SMALLINT UNSIGNED DEFAULT NULL,
    status           ENUM('active','inactive','deleted') NOT NULL DEFAULT 'active',
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    address          VARCHAR(511)  GENERATED ALWAYS AS (
                       TRIM(CONCAT_WS(', ',
                         NULLIF(address_line1,''),
                         NULLIF(address_line2,''),
                         NULLIF(address_city,''),
                         NULLIF(address_county,'')))
                     ) VIRTUAL,
    INDEX idx_properties_landlord (landlord_id),
    INDEX idx_properties_status   (status),
    FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE RESTRICT,
    FOREIGN KEY (manager_id)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UNITS
-- rent_amount = the unit's listed price.
-- PropertyService.stats and LeaseService.find JOIN on u.rent_amount.
-- ============================================================
CREATE TABLE IF NOT EXISTS units (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id          INT UNSIGNED NOT NULL,
    unit_number          VARCHAR(20)  NOT NULL,
    floor                VARCHAR(10)  DEFAULT NULL,
    unit_type            ENUM('bedsitter','studio','1br','2br','3br','4br',
                              'penthouse','office','shop','warehouse')
                         NOT NULL DEFAULT '1br',
    bedrooms             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    bathrooms            TINYINT UNSIGNED NOT NULL DEFAULT 1,
    size_sqft            DECIMAL(8,2)  DEFAULT NULL,
    rent_amount          DECIMAL(10,2) NOT NULL,
    deposit_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    water_included       TINYINT(1)   NOT NULL DEFAULT 0,
    electricity_included TINYINT(1)   NOT NULL DEFAULT 0,
    amenities            TEXT          DEFAULT NULL,
    description          TEXT          DEFAULT NULL,
    status               ENUM('available','occupied','maintenance','reserved')
                         NOT NULL DEFAULT 'available',
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_unit (property_id, unit_number),
    INDEX idx_units_property (property_id),
    INDEX idx_units_status   (status),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TENANTS
-- Own first_name/last_name/email/phone — TenantService.create requires these.
-- user_id nullable: tenants without portal accounts are supported.
-- ============================================================
CREATE TABLE IF NOT EXISTS tenants (
    id                      INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT UNSIGNED  DEFAULT NULL,
    first_name              VARCHAR(80)   NOT NULL,
    last_name               VARCHAR(80)   NOT NULL,
    email                   VARCHAR(150)  NOT NULL UNIQUE,
    phone                   VARCHAR(20)   NOT NULL,
    id_number               VARCHAR(20)   NOT NULL UNIQUE,
    id_type                 ENUM('national_id','passport','military','alien')
                            NOT NULL DEFAULT 'national_id',
    dob                     DATE          DEFAULT NULL,
    gender                  ENUM('male','female','other') DEFAULT NULL,
    nationality             VARCHAR(80)   DEFAULT NULL,
    emergency_contact_name  VARCHAR(150)  DEFAULT NULL,
    emergency_contact_phone VARCHAR(20)   DEFAULT NULL,
    next_of_kin_name        VARCHAR(150)  DEFAULT NULL,
    next_of_kin_phone       VARCHAR(20)   DEFAULT NULL,
    occupation              VARCHAR(100)  DEFAULT NULL,
    employer                VARCHAR(150)  DEFAULT NULL,
    monthly_income          DECIMAL(10,2) DEFAULT NULL,
    notes                   TEXT          DEFAULT NULL,
    status                  ENUM('active','inactive','blacklisted') NOT NULL DEFAULT 'active',
    created_at              TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenants_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LEASES
-- monthly_rent      — LeaseService.create / bulk invoice endpoint
-- payment_day       — LeaseService.create
-- grace_period_days — LeaseService.create
-- penalty_rate      — LeaseService.create
-- terminated_at     — LeaseService.terminate()
-- deposit_paid_date — LeaseService.create
-- ============================================================
CREATE TABLE IF NOT EXISTS leases (
    id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    lease_number       VARCHAR(30)   NOT NULL UNIQUE,
    unit_id            INT UNSIGNED  NOT NULL,
    tenant_id          INT UNSIGNED  NOT NULL,
    start_date         DATE          NOT NULL,
    end_date           DATE          NOT NULL,
    monthly_rent       DECIMAL(10,2) NOT NULL,
    deposit_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_paid_date  DATE          DEFAULT NULL,
    payment_day        TINYINT       NOT NULL DEFAULT 1,
    grace_period_days  TINYINT       NOT NULL DEFAULT 5,
    penalty_rate       DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    lease_type         ENUM('monthly','annual','short_term') NOT NULL DEFAULT 'monthly',
    status             ENUM('active','expired','terminated','pending') NOT NULL DEFAULT 'pending',
    termination_reason TEXT          DEFAULT NULL,
    terminated_at      DATETIME      DEFAULT NULL,
    terms              TEXT          DEFAULT NULL,
    notes              TEXT          DEFAULT NULL,
    created_by         INT UNSIGNED  DEFAULT NULL,
    created_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_leases_unit     (unit_id),
    INDEX idx_leases_tenant   (tenant_id),
    INDEX idx_leases_status   (status),
    INDEX idx_leases_end_date (end_date),
    FOREIGN KEY (unit_id)    REFERENCES units(id)    ON DELETE RESTRICT,
    FOREIGN KEY (tenant_id)  REFERENCES tenants(id)  ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INVOICES
-- invoice_date    — TenantService.getStatement, LeaseService.find
-- rent_amount     — bulk invoice endpoint inserts this
-- utility_amount  — invoices endpoint create/update
-- discount_amount — invoices endpoint create/update
-- status 'unpaid' and 'partial' used by TenantService
-- ============================================================
CREATE TABLE IF NOT EXISTS invoices (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(30)   NOT NULL UNIQUE,
    lease_id        INT UNSIGNED  NOT NULL,
    tenant_id       INT UNSIGNED  NOT NULL,
    invoice_date    DATE          NOT NULL,
    due_date        DATE          NOT NULL,
    rent_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    utility_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    penalty_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(10,2) NOT NULL,
    amount_paid     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status          ENUM('unpaid','partial','paid','overdue','cancelled','draft')
                    NOT NULL DEFAULT 'unpaid',
    notes           TEXT          DEFAULT NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoices_lease  (lease_id),
    INDEX idx_invoices_tenant (tenant_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_date   (invoice_date),
    INDEX idx_invoices_due    (due_date),
    FOREIGN KEY (lease_id)  REFERENCES leases(id)  ON DELETE RESTRICT,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENTS
-- payment_ref          — PaymentService.record (not payment_reference)
-- mpesa_transaction_id — PaymentService.record
-- cheque_number        — PaymentService.record
-- No unit_id column — unit resolved via lease JOIN
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id                   INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    payment_ref          VARCHAR(30)   NOT NULL UNIQUE,
    invoice_id           INT UNSIGNED  DEFAULT NULL,
    lease_id             INT UNSIGNED  NOT NULL,
    tenant_id            INT UNSIGNED  NOT NULL,
    amount               DECIMAL(10,2) NOT NULL,
    payment_date         DATE          NOT NULL,
    payment_type         ENUM('rent','deposit','water','electricity',
                              'maintenance','penalty','other')
                         NOT NULL DEFAULT 'rent',
    payment_method       ENUM('cash','mpesa','bank','cheque','card')
                         NOT NULL DEFAULT 'mpesa',
    mpesa_transaction_id VARCHAR(50)   DEFAULT NULL,
    cheque_number        VARCHAR(50)   DEFAULT NULL,
    status               ENUM('pending','completed','failed','reversed')
                         NOT NULL DEFAULT 'completed',
    received_by          INT UNSIGNED  DEFAULT NULL,
    notes                TEXT          DEFAULT NULL,
    created_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payments_lease   (lease_id),
    INDEX idx_payments_tenant  (tenant_id),
    INDEX idx_payments_date    (payment_date),
    INDEX idx_payments_invoice (invoice_id),
    FOREIGN KEY (invoice_id)  REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (lease_id)    REFERENCES leases(id)   ON DELETE RESTRICT,
    FOREIGN KEY (tenant_id)   REFERENCES tenants(id)  ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MAINTENANCE REQUESTS
-- issue_title     — MaintenanceService.create (not 'title')
-- work_started    — MaintenanceService.update (auto-set on in_progress)
-- work_completed  — MaintenanceService.update (auto-set on completed/resolved)
-- labour_hours, materials_cost, labour_cost, contractor_* — update fields
-- is_recurring, next_due_date — recurring maintenance support
-- ============================================================
CREATE TABLE IF NOT EXISTS maintenance_requests (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    request_number   VARCHAR(30)   NOT NULL UNIQUE,
    unit_id          INT UNSIGNED  NOT NULL,
    tenant_id        INT UNSIGNED  DEFAULT NULL,
    reported_by      INT UNSIGNED  DEFAULT NULL,
    issue_title      VARCHAR(200)  NOT NULL,
    description      TEXT          DEFAULT NULL,
    category         ENUM('plumbing','electrical','structural','pest_control',
                          'appliance','painting','cleaning','security','other')
                     NOT NULL DEFAULT 'other',
    priority         ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status           ENUM('open','assigned','in_progress','completed','resolved',
                          'closed','cancelled')
                     NOT NULL DEFAULT 'open',
    assigned_to      INT UNSIGNED  DEFAULT NULL,
    work_started     DATETIME      DEFAULT NULL,
    work_completed   DATETIME      DEFAULT NULL,
    labour_hours     DECIMAL(6,2)  DEFAULT NULL,
    materials_cost   DECIMAL(10,2) DEFAULT NULL,
    labour_cost      DECIMAL(10,2) DEFAULT NULL,
    contractor_name  VARCHAR(150)  DEFAULT NULL,
    contractor_phone VARCHAR(20)   DEFAULT NULL,
    is_recurring     TINYINT(1)    NOT NULL DEFAULT 0,
    next_due_date    DATE          DEFAULT NULL,
    notes            TEXT          DEFAULT NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_maint_unit     (unit_id),
    INDEX idx_maint_status   (status),
    INDEX idx_maint_assigned (assigned_to),
    FOREIGN KEY (unit_id)     REFERENCES units(id)   ON DELETE RESTRICT,
    FOREIGN KEY (tenant_id)   REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES users(id)   ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MPESA TRANSACTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id                  INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    payment_id          INT UNSIGNED  DEFAULT NULL,
    checkout_request_id VARCHAR(100)  DEFAULT NULL,
    merchant_request_id VARCHAR(100)  DEFAULT NULL,
    mpesa_receipt       VARCHAR(20)   DEFAULT NULL,
    phone               VARCHAR(20)   NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    account_reference   VARCHAR(50)   DEFAULT NULL,
    transaction_desc    VARCHAR(100)  DEFAULT NULL,
    status              ENUM('pending','completed','failed','cancelled')
                        NOT NULL DEFAULT 'pending',
    result_code         INT           DEFAULT NULL,
    result_desc         TEXT          DEFAULT NULL,
    raw_response        TEXT          DEFAULT NULL,
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  NOT NULL,
    title      VARCHAR(200)  NOT NULL,
    message    TEXT          NOT NULL,
    type       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    link       VARCHAR(255)  DEFAULT NULL,
    is_read    TINYINT(1)    NOT NULL DEFAULT 0,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UTILITY READINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS utility_readings (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    unit_id          INT UNSIGNED  NOT NULL,
    reading_type     ENUM('water','electricity') NOT NULL,
    previous_reading DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    current_reading  DECIMAL(10,2) NOT NULL,
    units_consumed   DECIMAL(10,2) GENERATED ALWAYS AS (current_reading - previous_reading) STORED,
    rate_per_unit    DECIMAL(8,2)  NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    reading_date     DATE          NOT NULL,
    period_month     TINYINT       NOT NULL,
    period_year      SMALLINT      NOT NULL,
    read_by          INT UNSIGNED  DEFAULT NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT,
    FOREIGN KEY (read_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  DEFAULT NULL,
    action     VARCHAR(100)  NOT NULL,
    table_name VARCHAR(100)  NOT NULL,
    record_id  INT UNSIGNED  DEFAULT NULL,
    old_values JSON          DEFAULT NULL,
    new_values JSON          DEFAULT NULL,
    ip_address VARCHAR(45)   DEFAULT NULL,
    user_agent VARCHAR(255)  DEFAULT NULL,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_table (table_name, record_id),
    INDEX idx_audit_user  (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXPENSES
-- ============================================================
CREATE TABLE IF NOT EXISTS expenses (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    property_id    INT UNSIGNED  DEFAULT NULL,
    unit_id        INT UNSIGNED  DEFAULT NULL,
    category       VARCHAR(100)  NOT NULL,
    description    TEXT          DEFAULT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    expense_date   DATE          NOT NULL,
    paid_by        INT UNSIGNED  DEFAULT NULL,
    receipt_number VARCHAR(50)   DEFAULT NULL,
    notes          TEXT          DEFAULT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expenses_property (property_id),
    INDEX idx_expenses_date     (expense_date),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id)     REFERENCES units(id)      ON DELETE SET NULL,
    FOREIGN KEY (paid_by)     REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VISITOR LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS visitor_logs (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    visitor_name   VARCHAR(150)  NOT NULL,
    visitor_phone  VARCHAR(20)   DEFAULT NULL,
    visitor_id_no  VARCHAR(50)   DEFAULT NULL,
    host_tenant_id INT UNSIGNED  DEFAULT NULL,
    unit_id        INT UNSIGNED  DEFAULT NULL,
    purpose        VARCHAR(200)  DEFAULT NULL,
    vehicle_reg    VARCHAR(20)   DEFAULT NULL,
    check_in       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    check_out      DATETIME      DEFAULT NULL,
    recorded_by    INT UNSIGNED  DEFAULT NULL,
    notes          TEXT          DEFAULT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visitor_checkin (check_in),
    FOREIGN KEY (host_tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id)        REFERENCES units(id)   ON DELETE SET NULL,
    FOREIGN KEY (recorded_by)    REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OCCUPANCY LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS occupancy_logs (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED  NOT NULL,
    unit_id     INT UNSIGNED  NOT NULL,
    event_type  ENUM('check_in','check_out','overnight_guest','extended_absence') NOT NULL,
    event_time  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes       TEXT          DEFAULT NULL,
    recorded_by INT UNSIGNED  DEFAULT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)   REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id)     REFERENCES units(id)   ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECURITY INCIDENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS security_incidents (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    property_id   INT UNSIGNED  DEFAULT NULL,
    unit_id       INT UNSIGNED  DEFAULT NULL,
    incident_type VARCHAR(100)  NOT NULL,
    severity      ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    description   TEXT          NOT NULL,
    action_taken  TEXT          DEFAULT NULL,
    reported_by   INT UNSIGNED  DEFAULT NULL,
    incident_time DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved      TINYINT(1)    NOT NULL DEFAULT 0,
    resolved_at   DATETIME      DEFAULT NULL,
    resolved_by   INT UNSIGNED  DEFAULT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_incidents_property (property_id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id)     REFERENCES units(id)      ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES users(id)      ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API TOKENS
-- revoked — ApiAuth.resolve queries `t.revoked = 0`
--           ApiAuth logout/revoke sets `revoked = 1`
-- ============================================================
CREATE TABLE IF NOT EXISTS api_tokens (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  NOT NULL,
    name       VARCHAR(100)  NOT NULL,
    token      VARCHAR(128)  NOT NULL UNIQUE,
    scopes     SET(
                 'read:properties','write:properties',
                 'read:units','write:units',
                 'read:tenants','write:tenants',
                 'read:leases','write:leases',
                 'read:invoices','write:invoices',
                 'read:payments','write:payments',
                 'read:maintenance','write:maintenance',
                 'read:reports',
                 'write:users',
                 'admin',
                 'all'
               ) NOT NULL DEFAULT 'all',
    last_used  DATETIME      DEFAULT NULL,
    expires_at DATETIME      DEFAULT NULL,
    revoked    TINYINT(1)    NOT NULL DEFAULT 0,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_tokens_user    (user_id),
    INDEX idx_api_tokens_revoked (revoked, expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API REQUEST LOGS
-- Columns match ApiAuth::logRequest() exactly:
--   (token_id, user_id, method, endpoint, status_code, ip_address, user_agent)
-- ============================================================
CREATE TABLE IF NOT EXISTS api_request_logs (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    token_id    INT UNSIGNED  DEFAULT NULL,
    user_id     INT UNSIGNED  DEFAULT NULL,
    method      VARCHAR(10)   NOT NULL,
    endpoint    VARCHAR(255)  NOT NULL,
    status_code SMALLINT      NOT NULL DEFAULT 0,
    duration_ms INT           DEFAULT NULL,
    ip_address  VARCHAR(45)   DEFAULT NULL,
    user_agent  VARCHAR(255)  DEFAULT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_logs_token   (token_id),
    INDEX idx_api_logs_created (created_at),
    FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id)  REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API RATE LIMITS
-- identifier = 'token:<id>' or 'ip:<addr>'
-- Matches ApiAuth::rateLimit() INSERT/SELECT logic exactly.
-- ============================================================
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier    VARCHAR(100) NOT NULL,
    window_start  DATETIME     NOT NULL,
    request_count INT          NOT NULL DEFAULT 1,
    UNIQUE KEY uq_identifier_window (identifier, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT         DEFAULT NULL,
    setting_group VARCHAR(50)  NOT NULL DEFAULT 'general',
    description   VARCHAR(255) DEFAULT NULL,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, description) VALUES
('company_name',         'RUMS Property Management', 'general',  'Company name'),
('company_email',        'info@rums.co.ke',           'general',  'Company email'),
('company_phone',        '0700000000',                'general',  'Company phone'),
('company_address',      'Nairobi, Kenya',            'general',  'Company address'),
('currency',             'KES',                       'general',  'Currency code'),
('currency_symbol',      'Ksh',                       'general',  'Currency symbol'),
('late_payment_penalty', '5',                         'payments', 'Late payment penalty %'),
('grace_period_days',    '5',                         'payments', 'Grace period days before penalty'),
('mpesa_consumer_key',   '',                          'mpesa',    'M-Pesa consumer key'),
('mpesa_consumer_secret','',                          'mpesa',    'M-Pesa consumer secret'),
('mpesa_shortcode',      '',                          'mpesa',    'M-Pesa shortcode (paybill/till)'),
('mpesa_passkey',        '',                          'mpesa',    'M-Pesa passkey'),
('mpesa_env',            'sandbox',                   'mpesa',    'M-Pesa environment (sandbox/live)'),
('mpesa_callback_url',   '',                          'mpesa',    'M-Pesa callback URL'),
('smtp_host',            '',                          'email',    'SMTP host'),
('smtp_port',            '587',                       'email',    'SMTP port'),
('smtp_user',            '',                          'email',    'SMTP username'),
('smtp_pass',            '',                          'email',    'SMTP password'),
('sms_api_key',          '',                          'sms',      'SMS API key (Africa''s Talking)'),
('sms_sender',           'RUMS',                      'sms',      'SMS sender ID');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF SCHEMA
-- 21 tables · all indexes inline · safe to import on fresh DB
-- ============================================================
