CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `password_changed_at` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `deleted_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `group_name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
    `role_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `token_type` VARCHAR(20) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_api_tokens_hash` (`token_hash`),
    INDEX `idx_api_tokens_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_login_attempts_identifier` (`identifier`),
    INDEX `idx_login_attempts_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pw_reset_hash` (`token_hash`),
    INDEX `idx_pw_reset_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_type` VARCHAR(50) NOT NULL,
    `actor_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` BIGINT UNSIGNED NULL,
    `before_json` TEXT NULL,
    `after_json` TEXT NULL,
    `reason` VARCHAR(255) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `request_id` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_actor` (`actor_type`, `actor_id`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_idempotency_keys` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `response_body` TEXT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_idempotency_key` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_class` VARCHAR(255) NOT NULL,
    `payload` TEXT NOT NULL,
    `status` ENUM('PENDING', 'RUNNING', 'SUCCESS', 'DEAD') DEFAULT 'PENDING',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `available_at` DATETIME NOT NULL,
    `started_at` DATETIME NULL,
    `finished_at` DATETIME NULL,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_job_queue_status_avail` (`status`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
