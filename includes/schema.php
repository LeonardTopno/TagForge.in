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
            'code' => 'monthly',
            'name' => 'Monthly Unlimited',
            'description' => 'Unlimited tag creation for Rs. 599 per month. Choose how many months you want to buy.',
            'price_inr' => 599,
            'tag_credits' => 0,
            'validity_days' => 30,
            'is_unlimited' => 1,
            'sort_order' => 1,
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
            tag_width_mm DECIMAL(6,2) NOT NULL DEFAULT 64.00,
            tag_height_mm DECIMAL(6,2) NOT NULL DEFAULT 18.00,
            font_size_pt DECIMAL(5,2) NOT NULL DEFAULT 8.00,
            horizontal_offset_mm DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            vertical_offset_mm DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            show_shop_name TINYINT(1) NOT NULL DEFAULT 1,
            tag_credit_balance INT NOT NULL DEFAULT 20,
            credits_expire_at DATETIME DEFAULT NULL,
            unlimited_until DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            suspended_reason VARCHAR(240) DEFAULT NULL,
            last_active_at DATETIME DEFAULT NULL,
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
            is_system TINYINT(1) NOT NULL DEFAULT 0,
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
            status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(40) NOT NULL DEFAULT 'razorpay',
            razorpay_order_id VARCHAR(120) DEFAULT NULL,
            razorpay_payment_id VARCHAR(120) DEFAULT NULL,
            notes TEXT,
            receipt_note VARCHAR(240) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME DEFAULT NULL,
            refunded_at DATETIME DEFAULT NULL,
            recorded_by INT UNSIGNED DEFAULT NULL,
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

        "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_password_reset_token_hash (token_hash),
            KEY idx_password_reset_user (user_id),
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

function ensure_password_reset_schema($pdo = null)
{
    $pdo = $pdo ?: db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_password_reset_token_hash (token_hash),
            KEY idx_password_reset_user (user_id),
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function table_has_column(PDO $pdo, $table, $column)
{
    // MySQL rejects placeholders in SHOW COLUMNS with native prepares
    // (PDO::ATTR_EMULATE_PREPARES = false). Use information_schema instead.
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute(array($table, $column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function ensure_admin_platform_schema($pdo = null)
{
    $pdo = $pdo ?: db();

    if (!table_has_column($pdo, 'shops', 'is_active')) {
        $pdo->exec("ALTER TABLE shops ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER unlimited_until");
    }
    if (!table_has_column($pdo, 'shops', 'suspended_reason')) {
        $pdo->exec("ALTER TABLE shops ADD COLUMN suspended_reason VARCHAR(240) DEFAULT NULL AFTER is_active");
    }
    if (!table_has_column($pdo, 'shops', 'last_active_at')) {
        $pdo->exec("ALTER TABLE shops ADD COLUMN last_active_at DATETIME DEFAULT NULL AFTER suspended_reason");
    }

    if (!table_has_column($pdo, 'plan_purchases', 'payment_method')) {
        $pdo->exec("ALTER TABLE plan_purchases ADD COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT 'razorpay' AFTER status");
    }
    if (!table_has_column($pdo, 'plan_purchases', 'receipt_note')) {
        $pdo->exec("ALTER TABLE plan_purchases ADD COLUMN receipt_note VARCHAR(240) DEFAULT NULL AFTER notes");
    }
    if (!table_has_column($pdo, 'plan_purchases', 'refunded_at')) {
        $pdo->exec("ALTER TABLE plan_purchases ADD COLUMN refunded_at DATETIME DEFAULT NULL AFTER paid_at");
    }
    if (!table_has_column($pdo, 'plan_purchases', 'recorded_by')) {
        $pdo->exec("ALTER TABLE plan_purchases ADD COLUMN recorded_by INT UNSIGNED DEFAULT NULL AFTER refunded_at");
    }

    // Expand purchase status to include refunded when possible.
    try {
        $pdo->exec("ALTER TABLE plan_purchases MODIFY status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending'");
    } catch (Exception $e) {
        // Ignore if MySQL version/permissions block ENUM change; refunded_at still works.
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS shop_support_notes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            shop_id INT UNSIGNED NOT NULL,
            author_user_id INT UNSIGNED NOT NULL,
            body VARCHAR(1000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_support_notes_shop (shop_id),
            CONSTRAINT fk_support_notes_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
            CONSTRAINT fk_support_notes_author FOREIGN KEY (author_user_id) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS support_view_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash CHAR(64) NOT NULL,
            admin_user_id INT UNSIGNED NOT NULL,
            shop_id INT UNSIGNED NOT NULL,
            target_user_id INT UNSIGNED NOT NULL,
            mode ENUM('readonly','timed') NOT NULL DEFAULT 'readonly',
            duration_minutes INT NOT NULL DEFAULT 30,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_support_view_token (token_hash),
            KEY idx_support_view_shop (shop_id),
            CONSTRAINT fk_support_view_admin FOREIGN KEY (admin_user_id) REFERENCES users (id),
            CONSTRAINT fk_support_view_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
            CONSTRAINT fk_support_view_target FOREIGN KEY (target_user_id) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    sync_measured_tag_defaults($pdo);
}

/**
 * Align platform + shop tag width to measured Bin Ismail roll (64 mm printable, fold at 32 mm).
 */
function sync_measured_tag_defaults($pdo = null)
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    try {
        $pdo->exec(
            "UPDATE platform_settings SET setting_value = '64.00'
             WHERE setting_key = 'default_tag_width_mm' AND setting_value IN ('80.00', '80')"
        );
        $pdo->exec(
            'UPDATE shops SET tag_width_mm = 64.00 WHERE tag_width_mm IN (80.00, 80)'
        );
    } catch (Exception $e) {
        // Non-fatal dimension sync.
    }
    $done = true;
}

function seed_billing_plans(PDO $pdo)
{
    sync_billing_plans($pdo);
}

function sync_billing_plans($pdo = null)
{
    $pdo = $pdo ?: db();
    // Only seed missing system plans. Never overwrite admin edits or deactivate custom plans.
    foreach (default_billing_plans() as $plan) {
        $existing = $pdo->prepare('SELECT id FROM billing_plans WHERE code = ?');
        $existing->execute(array($plan['code']));
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (table_has_column($pdo, 'billing_plans', 'is_system')) {
                $pdo->prepare('UPDATE billing_plans SET is_system = 1 WHERE id = ?')->execute(array($row['id']));
            }
            continue;
        }
        if (table_has_column($pdo, 'billing_plans', 'is_system')) {
            $pdo->prepare(
                'INSERT INTO billing_plans (code, name, description, price_inr, tag_credits, validity_days, is_unlimited, is_active, is_system, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?)'
            )->execute(array(
                $plan['code'], $plan['name'], $plan['description'], $plan['price_inr'],
                $plan['tag_credits'], $plan['validity_days'], $plan['is_unlimited'], $plan['sort_order'],
            ));
        } else {
            $pdo->prepare(
                'INSERT INTO billing_plans (code, name, description, price_inr, tag_credits, validity_days, is_unlimited, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)'
            )->execute(array(
                $plan['code'], $plan['name'], $plan['description'], $plan['price_inr'],
                $plan['tag_credits'], $plan['validity_days'], $plan['is_unlimited'], $plan['sort_order'],
            ));
        }
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
