<?php

function default_jewellery_items()
{
    return array(
        'Ring', 'Tops', 'Earrings', 'Necklace', 'Chain', 'Pendant', 'Bangle',
        'Bracelet', 'Kada', 'Mangalsutra', 'Nose Pin', 'Anklet', 'Coin', 'Bar', 'Set',
    );
}

function default_billing_plans()
{
    return array(
        array(
            'code' => 'starter',
            'name' => 'Starter',
            'description' => '500 tag credits for focused shop usage.',
            'price_inr' => 1000,
            'tag_credits' => 500,
            'validity_days' => 90,
            'is_unlimited' => 0,
            'sort_order' => 1,
        ),
        array(
            'code' => 'growth',
            'name' => 'Growth',
            'description' => '750 tag credits for higher monthly printing.',
            'price_inr' => 1500,
            'tag_credits' => 750,
            'validity_days' => 90,
            'is_unlimited' => 0,
            'sort_order' => 2,
        ),
        array(
            'code' => 'pro',
            'name' => 'Pro Annual',
            'description' => 'Unlimited tag printing for one year.',
            'price_inr' => 10000,
            'tag_credits' => 0,
            'validity_days' => 365,
            'is_unlimited' => 1,
            'sort_order' => 3,
        ),
    );
}

function install_schema(PDO $pdo)
{
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $statements = array(
        "CREATE TABLE IF NOT EXISTS shops (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(160) NOT NULL,
            address VARCHAR(255) NOT NULL DEFAULT '',
            phone_number VARCHAR(20) NOT NULL DEFAULT '',
            gst_no VARCHAR(20) NOT NULL DEFAULT '',
            logo_path VARCHAR(255) DEFAULT NULL,
            short_name VARCHAR(40) NOT NULL,
            tag_prefix VARCHAR(12) NOT NULL DEFAULT 'T',
            next_tag_number INT NOT NULL DEFAULT 1,
            tag_width_mm DECIMAL(6,2) NOT NULL DEFAULT 80.00,
            tag_height_mm DECIMAL(6,2) NOT NULL DEFAULT 18.00,
            font_size_pt DECIMAL(5,2) NOT NULL DEFAULT 8.00,
            horizontal_offset_mm DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            vertical_offset_mm DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            show_shop_name TINYINT(1) NOT NULL DEFAULT 1,
            tag_credit_balance INT NOT NULL DEFAULT 15,
            credits_expire_at DATETIME DEFAULT NULL,
            unlimited_until DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('owner','operator','admin') NOT NULL DEFAULT 'owner',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_email (email),
            KEY idx_users_shop (shop_id),
            CONSTRAINT fk_users_shop FOREIGN KEY (shop_id) REFERENCES shops (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS shop_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_shop_item_name (shop_id, name),
            KEY idx_shop_items_shop (shop_id),
            CONSTRAINT fk_shop_items_shop FOREIGN KEY (shop_id) REFERENCES shops (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS jewellery_tags (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id INT UNSIGNED NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            tag_number VARCHAR(50) NOT NULL,
            item_name VARCHAR(100) NOT NULL,
            category VARCHAR(60) DEFAULT NULL,
            purity VARCHAR(30) DEFAULT NULL,
            pieces INT NOT NULL DEFAULT 1,
            gross_weight DECIMAL(10,3) NOT NULL,
            stone_weight DECIMAL(10,3) NOT NULL DEFAULT 0.000,
            other_deduction DECIMAL(10,3) NOT NULL DEFAULT 0.000,
            net_weight DECIMAL(10,3) NOT NULL,
            copies INT NOT NULL DEFAULT 1,
            status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
            print_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_shop_tag_number (shop_id, tag_number),
            KEY idx_tags_shop (shop_id),
            CONSTRAINT fk_tags_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
            CONSTRAINT fk_tags_user FOREIGN KEY (created_by) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS print_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id INT UNSIGNED NOT NULL,
            jewellery_tag_id INT UNSIGNED NOT NULL,
            printed_by INT UNSIGNED NOT NULL,
            copies INT NOT NULL DEFAULT 1,
            print_status ENUM('requested','printed') NOT NULL DEFAULT 'printed',
            printed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_print_logs_shop (shop_id),
            CONSTRAINT fk_print_logs_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
            CONSTRAINT fk_print_logs_tag FOREIGN KEY (jewellery_tag_id) REFERENCES jewellery_tags (id),
            CONSTRAINT fk_print_logs_user FOREIGN KEY (printed_by) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS billing_plans (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(40) NOT NULL,
            name VARCHAR(80) NOT NULL,
            description VARCHAR(240) NOT NULL,
            price_inr INT NOT NULL,
            tag_credits INT NOT NULL DEFAULT 0,
            validity_days INT NOT NULL,
            is_unlimited TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_billing_plans_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS plan_purchases (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            amount_inr INT NOT NULL,
            tag_credits INT NOT NULL,
            validity_days INT NOT NULL,
            is_unlimited TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
            razorpay_order_id VARCHAR(120) DEFAULT NULL,
            razorpay_payment_id VARCHAR(120) DEFAULT NULL,
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_purchases_shop (shop_id),
            CONSTRAINT fk_purchases_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
            CONSTRAINT fk_purchases_plan FOREIGN KEY (plan_id) REFERENCES billing_plans (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS credit_ledger_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id INT UNSIGNED NOT NULL,
            entry_type ENUM('credit','debit') NOT NULL,
            credits INT NOT NULL,
            balance_after INT NOT NULL,
            description VARCHAR(240) NOT NULL,
            jewellery_tag_id INT UNSIGNED DEFAULT NULL,
            purchase_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ledger_shop (shop_id),
            CONSTRAINT fk_ledger_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
            CONSTRAINT fk_ledger_tag FOREIGN KEY (jewellery_tag_id) REFERENCES jewellery_tags (id),
            CONSTRAINT fk_ledger_purchase FOREIGN KEY (purchase_id) REFERENCES plan_purchases (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

function seed_billing_plans(PDO $pdo)
{
    $stmt = $pdo->prepare(
        'INSERT INTO billing_plans (code, name, description, price_inr, tag_credits, validity_days, is_unlimited, sort_order)
         SELECT ?, ?, ?, ?, ?, ?, ?, ?
         FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM billing_plans WHERE code = ?)'
    );
    foreach (default_billing_plans() as $plan) {
        $stmt->execute(array(
            $plan['code'], $plan['name'], $plan['description'], $plan['price_inr'],
            $plan['tag_credits'], $plan['validity_days'], $plan['is_unlimited'], $plan['sort_order'],
            $plan['code'],
        ));
    }
}

function seed_items_for_shop(PDO $pdo, $shopId)
{
    $count = $pdo->prepare('SELECT COUNT(*) FROM shop_items WHERE shop_id = ?');
    $count->execute(array($shopId));
    if ((int) $count->fetchColumn() > 0) {
        return;
    }
    $insert = $pdo->prepare('INSERT INTO shop_items (shop_id, name, sort_order) VALUES (?, ?, ?)');
    foreach (default_jewellery_items() as $index => $name) {
        $insert->execute(array($shopId, $name, $index));
    }
}
