CREATE TABLE `territories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('zone', 'region', 'hq', 'territory') NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_terr_parent` FOREIGN KEY (`parent_id`) REFERENCES `territories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku_code` VARCHAR(100) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_prod_sku` (`sku_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('doctor', 'chemist', 'stockist') NOT NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(50) NULL,
    `specialty` VARCHAR(100) NULL,
    `territory_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_cust_terr` FOREIGN KEY (`territory_id`) REFERENCES `territories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_territories` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `territory_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `territory_id`),
    CONSTRAINT `fk_ut_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ut_terr` FOREIGN KEY (`territory_id`) REFERENCES `territories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
