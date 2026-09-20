CREATE TABLE `dcrs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `territory_id` BIGINT UNSIGNED NOT NULL,
    `dcr_date` DATE NOT NULL,
    `status` ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'draft',
    `manager_notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_dcr_user_date` (`user_id`, `dcr_date`),
    CONSTRAINT `fk_dcr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dcr_territory` FOREIGN KEY (`territory_id`) REFERENCES `territories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dcr_visits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dcr_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `visit_time` TIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_dcr_visit` (`dcr_id`, `customer_id`),
    CONSTRAINT `fk_dcr_visits_dcr` FOREIGN KEY (`dcr_id`) REFERENCES `dcrs` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dcr_visits_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dcr_products` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dcr_visit_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_dcr_product` (`dcr_visit_id`, `product_id`),
    CONSTRAINT `fk_dcr_products_visit` FOREIGN KEY (`dcr_visit_id`) REFERENCES `dcr_visits` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dcr_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
