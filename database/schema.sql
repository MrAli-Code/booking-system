-- ============================================================
-- BOOKING SYSTEM - COMPLETE DATABASE SCHEMA
-- Supports: SaaS Multi-Tenant, Standalone, WordPress Plugin
-- ============================================================

-- 1. CORE TABLES (always present)

-- 1.1 Tenants (SaaS mode only, standalone uses tenant_id=1)
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `domain` VARCHAR(255) DEFAULT NULL,
    `database_name` VARCHAR(64) DEFAULT NULL,
    `db_host` VARCHAR(255) DEFAULT NULL,
    `db_user` VARCHAR(64) DEFAULT NULL,
    `db_pass` VARCHAR(255) DEFAULT NULL,
    `plan` ENUM('basic','pro','enterprise','lifetime') NOT NULL DEFAULT 'basic',
    `status` ENUM('active','suspended','trial','disabled') NOT NULL DEFAULT 'trial',
    `branding_logo` VARCHAR(500) DEFAULT NULL,
    `branding_name` VARCHAR(255) DEFAULT NULL,
    `branding_tagline` VARCHAR(500) DEFAULT NULL,
    `branding_primary_color` VARCHAR(7) DEFAULT '#6366f1',
    `branding_secondary_color` VARCHAR(7) DEFAULT '#8b5cf6',
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `contact_email` VARCHAR(255) DEFAULT NULL,
    `contact_address` TEXT DEFAULT NULL,
    `contact_website` VARCHAR(255) DEFAULT NULL,
    `settings` JSON DEFAULT NULL,
    `license_key` TEXT DEFAULT NULL,
    `license_expires_at` DATETIME DEFAULT NULL,
    `features` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_tenants_status` (`status`),
    INDEX `idx_tenants_plan` (`plan`),
    INDEX `idx_tenants_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.2 Users (for admin/staff accounts)
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin','admin','manager','staff','receptionist') NOT NULL DEFAULT 'staff',
    `avatar` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `two_factor_secret` VARCHAR(255) DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login_at` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `remember_token` VARCHAR(255) DEFAULT NULL,
    `password_changed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE INDEX `idx_users_email_tenant` (`email`, `tenant_id`),
    INDEX `idx_users_tenant` (`tenant_id`),
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.3 Permissions / RBAC
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `group` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role` ENUM('super_admin','admin','manager','staff','receptionist') NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role`, `permission_id`, `tenant_id`),
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CUSTOMER / CRM TABLES

-- 2.1 Customers
CREATE TABLE IF NOT EXISTS `customers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `national_code` VARCHAR(20) DEFAULT NULL,
    `gender` ENUM('male','female','other') DEFAULT NULL,
    `birth_date` DATE DEFAULT NULL,
    `avatar` VARCHAR(500) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `referral_code` VARCHAR(20) DEFAULT NULL UNIQUE,
    `referred_by` BIGINT UNSIGNED DEFAULT NULL,
    `total_bookings` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_spent` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `wallet_balance` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `rating_avg` DECIMAL(3,2) DEFAULT 0.00,
    `rating_count` INT UNSIGNED DEFAULT 0,
    `tags` JSON DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_visit_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_customers_tenant` (`tenant_id`),
    INDEX `idx_customers_phone` (`phone`),
    INDEX `idx_customers_email` (`email`),
    INDEX `idx_customers_referral` (`referral_code`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`referred_by`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.2 Customer Addresses
CREATE TABLE IF NOT EXISTS `customer_addresses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(100) DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `address` TEXT NOT NULL,
    `postal_code` VARCHAR(20) DEFAULT NULL,
    `latitude` DECIMAL(10,8) DEFAULT NULL,
    `longitude` DECIMAL(11,8) DEFAULT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.3 Wallet Transactions
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('deposit','withdrawal','payment','refund','bonus','referral_reward') NOT NULL,
    `amount` DECIMAL(20,2) NOT NULL,
    `balance_before` DECIMAL(20,2) NOT NULL,
    `balance_after` DECIMAL(20,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IRR',
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` BIGINT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wallet_customer` (`customer_id`),
    INDEX `idx_wallet_tenant` (`tenant_id`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BOOKING ENGINE TABLES

-- 3.1 Services
CREATE TABLE IF NOT EXISTS `services` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category_id` BIGINT UNSIGNED DEFAULT NULL,
    `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
    `price` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `deposit_type` ENUM('none','fixed','percentage') NOT NULL DEFAULT 'none',
    `deposit_amount` DECIMAL(20,2) DEFAULT 0.00,
    `deposit_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IRR',
    `color` VARCHAR(7) DEFAULT '#6366f1',
    `icon` VARCHAR(100) DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `max_per_day` INT UNSIGNED DEFAULT NULL,
    `requires_review` TINYINT(1) NOT NULL DEFAULT 0,
    `allows_image_upload` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_services_tenant` (`tenant_id`),
    INDEX `idx_services_category` (`category_id`),
    INDEX `idx_services_active` (`is_active`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.2 Service Categories
CREATE TABLE IF NOT EXISTS `service_categories` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cat_tenant` (`tenant_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.3 Specialists
CREATE TABLE IF NOT EXISTS `specialists` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `avatar` VARCHAR(500) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `color` VARCHAR(7) DEFAULT '#6366f1',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_spec_tenant` (`tenant_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4 Specialist Services (Many-to-Many)
CREATE TABLE IF NOT EXISTS `specialist_services` (
    `specialist_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NOT NULL,
    `price_modifier` DECIMAL(10,2) DEFAULT 0.00,
    `duration_modifier` INT DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`specialist_id`, `service_id`),
    FOREIGN KEY (`specialist_id`) REFERENCES `specialists`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.5 Schedule (Time Slots)
CREATE TABLE IF NOT EXISTS `schedules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `specialist_id` BIGINT UNSIGNED DEFAULT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=Sun,1=Mon,...6=Sat',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `slot_duration` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'minutes',
    `break_start` TIME DEFAULT NULL,
    `break_end` TIME DEFAULT NULL,
    `max_bookings_per_slot` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `valid_from` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sched_tenant` (`tenant_id`),
    INDEX `idx_sched_specialist` (`specialist_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`specialist_id`) REFERENCES `specialists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.6 Schedule Exceptions (holidays, custom hours)
CREATE TABLE IF NOT EXISTS `schedule_exceptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `specialist_id` BIGINT UNSIGNED DEFAULT NULL,
    `date` DATE NOT NULL,
    `type` ENUM('holiday','custom_hours','full_booked') NOT NULL DEFAULT 'holiday',
    `start_time` TIME DEFAULT NULL,
    `end_time` TIME DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_exc_date` (`date`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`specialist_id`) REFERENCES `specialists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.7 Bookings
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `specialist_id` BIGINT UNSIGNED DEFAULT NULL,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `booking_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Format: BBS-A1B2C',
    `status` ENUM('pending','needs_review','confirmed','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    `booking_date` DATE NOT NULL,
    `booking_time` TIME NOT NULL,
    `total_duration` INT UNSIGNED NOT NULL COMMENT 'total minutes',
    `subtotal` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `discount_type` ENUM('none','combo','referral','coupon','custom') DEFAULT 'none',
    `deposit_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IRR',
    `notes` TEXT DEFAULT NULL,
    `admin_notes` TEXT DEFAULT NULL,
    `diagnostic_images` JSON DEFAULT NULL COMMENT 'medical mode images',
    `requires_review` TINYINT(1) NOT NULL DEFAULT 0,
    `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `cancelled_by` ENUM('customer','admin','system') DEFAULT NULL,
    `cancel_reason` TEXT DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `source` ENUM('website','widget','admin','api','telegram','whatsapp') DEFAULT 'website',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_book_tenant` (`tenant_id`),
    INDEX `idx_book_customer` (`customer_id`),
    INDEX `idx_book_specialist` (`specialist_id`),
    INDEX `idx_book_date` (`booking_date`),
    INDEX `idx_book_status` (`status`),
    INDEX `idx_book_code` (`booking_code`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`specialist_id`) REFERENCES `specialists`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.8 Booking Items (multi-service cart)
CREATE TABLE IF NOT EXISTS `booking_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NOT NULL,
    `specialist_id` BIGINT UNSIGNED DEFAULT NULL,
    `service_name` VARCHAR(255) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(20,2) NOT NULL,
    `duration_minutes` INT UNSIGNED NOT NULL,
    `discount_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(20,2) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bi_booking` (`booking_id`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.9 Booking History (Audit Trail)
CREATE TABLE IF NOT EXISTS `booking_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `from_status` VARCHAR(50) DEFAULT NULL,
    `to_status` VARCHAR(50) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `performed_by` BIGINT UNSIGNED DEFAULT NULL,
    `performed_by_type` ENUM('customer','user','system') DEFAULT 'system',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bh_booking` (`booking_id`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.10 Waitlist
CREATE TABLE IF NOT EXISTS `waitlist` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED DEFAULT NULL,
    `specialist_id` BIGINT UNSIGNED DEFAULT NULL,
    `preferred_date` DATE DEFAULT NULL,
    `preferred_time` TIME DEFAULT NULL,
    `status` ENUM('waiting','notified','converted','expired','cancelled') NOT NULL DEFAULT 'waiting',
    `notified_at` DATETIME DEFAULT NULL,
    `converted_booking_id` BIGINT UNSIGNED DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_wl_tenant` (`tenant_id`),
    INDEX `idx_wl_customer` (`customer_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`converted_booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PAYMENT TABLES

-- 4.1 Payments
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `booking_id` BIGINT UNSIGNED DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `transaction_id` VARCHAR(255) DEFAULT NULL COMMENT 'gateway transaction ID',
    `amount` DECIMAL(20,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IRR',
    `payment_method` ENUM('zarinpal','nextpay','idpay','card_to_card','wallet','crypto','cash','pos','manual') NOT NULL,
    `payment_type` ENUM('full','deposit','partial','installment') NOT NULL DEFAULT 'full',
    `status` ENUM('pending','processing','completed','failed','refunded','cancelled','expired') NOT NULL DEFAULT 'pending',
    `gateway_data` JSON DEFAULT NULL,
    `card_to_card_info` JSON DEFAULT NULL COMMENT 'receipt image, card number',
    `crypto_info` JSON DEFAULT NULL COMMENT 'wallet address, tx hash',
    `verification_data` JSON DEFAULT NULL,
    `verified_by` BIGINT UNSIGNED DEFAULT NULL,
    `verified_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `fail_reason` TEXT DEFAULT NULL,
    `refund_amount` DECIMAL(20,2) DEFAULT 0.00,
    `refunded_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pay_tenant` (`tenant_id`),
    INDEX `idx_pay_booking` (`booking_id`),
    INDEX `idx_pay_customer` (`customer_id`),
    INDEX `idx_pay_status` (`status`),
    INDEX `idx_pay_txn` (`transaction_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.2 Invoices
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `booking_id` BIGINT UNSIGNED DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `subtotal` DECIMAL(20,2) NOT NULL,
    `discount_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(20,2) NOT NULL,
    `paid_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `due_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IRR',
    `status` ENUM('draft','sent','paid','partial','overdue','cancelled','refunded') NOT NULL DEFAULT 'draft',
    `due_date` DATE DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `branding_data` JSON DEFAULT NULL COMMENT 'snapshot of branding at invoice time',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_inv_tenant` (`tenant_id`),
    INDEX `idx_inv_customer` (`customer_id`),
    INDEX `idx_inv_status` (`status`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.3 Discount Rules (Combo Engine)
CREATE TABLE IF NOT EXISTS `discount_rules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('combo','quantity','loyalty','coupon','referral','custom') NOT NULL,
    `trigger_type` ENUM('service_count','specific_services','total_amount','customer_loyalty','coupon_code') NOT NULL,
    `trigger_value` TEXT NOT NULL COMMENT 'JSON config',
    `discount_type` ENUM('percentage','fixed','service_free') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_services` INT UNSIGNED DEFAULT 1,
    `max_discount` DECIMAL(20,2) DEFAULT NULL,
    `valid_from` DATETIME DEFAULT NULL,
    `valid_until` DATETIME DEFAULT NULL,
    `usage_limit` INT UNSIGNED DEFAULT NULL,
    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_dr_tenant` (`tenant_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. NOTIFICATION TABLES

-- 5.1 Notification Logs
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `booking_id` BIGINT UNSIGNED DEFAULT NULL,
    `channel` ENUM('sms','telegram','whatsapp','email','bale','rubika','push') NOT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'reminder, confirmation, cancel, etc',
    `recipient` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('queued','sent','delivered','failed','read') NOT NULL DEFAULT 'queued',
    `provider_response` JSON DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nl_tenant` (`tenant_id`),
    INDEX `idx_nl_customer` (`customer_id`),
    INDEX `idx_nl_channel` (`channel`),
    INDEX `idx_nl_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.2 Notification Templates
CREATE TABLE IF NOT EXISTS `notification_templates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `type` VARCHAR(50) NOT NULL,
    `channel` ENUM('sms','telegram','whatsapp','email','bale','rubika') NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `body` TEXT NOT NULL,
    `variables` JSON DEFAULT NULL COMMENT 'available variables for this template',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_nt_tenant` (`tenant_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. LICENSE & SETTINGS TABLES

-- 6.1 Licenses
CREATE TABLE IF NOT EXISTS `licenses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `license_key` TEXT NOT NULL,
    `license_hash` VARCHAR(64) NOT NULL UNIQUE,
    `plan` ENUM('basic','pro','enterprise','lifetime') NOT NULL DEFAULT 'basic',
    `status` ENUM('active','expired','revoked','suspended') NOT NULL DEFAULT 'active',
    `domain` VARCHAR(255) DEFAULT NULL,
    `server_fingerprint` VARCHAR(255) DEFAULT NULL,
    `features` JSON DEFAULT NULL COMMENT 'enabled features from license',
    `issued_at` DATETIME NOT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `activated_at` DATETIME DEFAULT NULL,
    `last_validated_at` DATETIME DEFAULT NULL,
    `validation_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `metadata` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_lic_tenant` (`tenant_id`),
    INDEX `idx_lic_hash` (`license_hash`),
    INDEX `idx_lic_status` (`status`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.2 Settings (Key-Value)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `key` VARCHAR(255) NOT NULL,
    `value` LONGTEXT DEFAULT NULL,
    `group` VARCHAR(100) DEFAULT 'general',
    `type` ENUM('string','json','number','boolean','file') NOT NULL DEFAULT 'string',
    `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_set_tenant_key` (`tenant_id`, `key`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.3 Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `customer_id` BIGINT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `resource_type` VARCHAR(50) DEFAULT NULL,
    `resource_id` BIGINT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_al_tenant` (`tenant_id`),
    INDEX `idx_al_user` (`user_id`),
    INDEX `idx_al_action` (`action`),
    INDEX `idx_al_resource` (`resource_type`, `resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.4 Payment Gateways Config
CREATE TABLE IF NOT EXISTS `payment_gateways` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `gateway` VARCHAR(50) NOT NULL COMMENT 'zarinpal, nextpay, idpay, etc',
    `config` JSON NOT NULL COMMENT 'encrypted API keys, merchant IDs',
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_pg_tenant_gateway` (`tenant_id`, `gateway`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.5 Rating/Reviews
CREATE TABLE IF NOT EXISTS `ratings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `specialist_id` BIGINT UNSIGNED DEFAULT NULL,
    `rating` TINYINT UNSIGNED NOT NULL CHECK (rating >= 1 AND rating <= 5),
    `comment` TEXT DEFAULT NULL,
    `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_rating_booking` (`booking_id`),
    INDEX `idx_rating_specialist` (`specialist_id`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. WORDPRESS PLUGIN TABLES (prefixed with `bbs_`)

-- WordPress sync map (maps WP user IDs to system customer IDs)
CREATE TABLE IF NOT EXISTS `wp_user_sync` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `wp_user_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_wp_user` (`wp_user_id`),
    INDEX `idx_wp_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. REFERRAL SYSTEM
CREATE TABLE IF NOT EXISTS `referral_rewards` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `referrer_customer_id` BIGINT UNSIGNED NOT NULL,
    `referred_customer_id` BIGINT UNSIGNED NOT NULL,
    `booking_id` BIGINT UNSIGNED DEFAULT NULL,
    `reward_type` ENUM('credit','discount','service') NOT NULL DEFAULT 'credit',
    `reward_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rr_referrer` (`referrer_customer_id`),
    INDEX `idx_rr_referred` (`referred_customer_id`),
    FOREIGN KEY (`referrer_customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`referred_customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERT DEFAULT DATA
-- ============================================================

-- Default admin tenant
INSERT INTO `tenants` (`id`, `uuid`, `name`, `slug`, `plan`, `status`, `branding_name`, `features`) VALUES
(1, UUID(), 'Default', 'default', 'enterprise', 'active', 'My Clinic',
 '{"booking":true,"payment":true,"crm":true,"wallet":true,"referral":true,"notification":true,"report":true,"multi_currency":true,"crypto":false,"whatsapp":true,"telegram":true,"bale":true,"rubika":true}');

-- Default admin user (password: admin123)
INSERT INTO `users` (`tenant_id`, `uuid`, `name`, `email`, `phone`, `password`, `role`, `is_active`) VALUES
(1, UUID(), 'Admin', 'admin@example.com', '09120000000',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'super_admin', 1);

-- Default permissions
INSERT INTO `permissions` (`name`, `slug`, `group`) VALUES
('View Dashboard', 'dashboard.view', 'dashboard'),
('Manage Bookings', 'bookings.manage', 'bookings'),
('View Bookings', 'bookings.view', 'bookings'),
('Manage Customers', 'customers.manage', 'crm'),
('View Customers', 'customers.view', 'crm'),
('Manage Services', 'services.manage', 'services'),
('View Services', 'services.view', 'services'),
('Manage Specialists', 'specialists.manage', 'staff'),
('Manage Payments', 'payments.manage', 'finance'),
('View Payments', 'payments.view', 'finance'),
('Manage Settings', 'settings.manage', 'system'),
('Manage License', 'license.manage', 'system'),
('View Reports', 'reports.view', 'reports'),
('Manage Notifications', 'notifications.manage', 'notifications'),
('Manage Tenants', 'tenants.manage', 'saas');
