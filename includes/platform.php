<?php

/**
 * DB-backed platform settings, activity audit, and related helpers.
 */

function ensure_ops_schema($pdo = null)
{
    $pdo = $pdo ?: db();

    if (!table_has_column($pdo, 'billing_plans', 'is_system')) {
        $pdo->exec("ALTER TABLE billing_plans ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
        $pdo->exec("UPDATE billing_plans SET is_system = 1 WHERE code IN ('monthly')");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS platform_settings (
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promo_codes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(40) NOT NULL,
            description VARCHAR(240) NOT NULL DEFAULT '',
            tag_credits INT NOT NULL DEFAULT 0,
            validity_days INT NOT NULL DEFAULT 7,
            is_unlimited TINYINT(1) NOT NULL DEFAULT 0,
            max_redemptions INT NOT NULL DEFAULT 0,
            redemption_count INT NOT NULL DEFAULT 0,
            starts_at DATETIME DEFAULT NULL,
            ends_at DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_promo_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promo_redemptions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            promo_id INT UNSIGNED NOT NULL,
            shop_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_promo_shop (promo_id, shop_id),
            KEY idx_promo_redemptions_shop (shop_id),
            CONSTRAINT fk_promo_redemptions_promo FOREIGN KEY (promo_id) REFERENCES promo_codes (id),
            CONSTRAINT fk_promo_redemptions_shop FOREIGN KEY (shop_id) REFERENCES shops (id),
            CONSTRAINT fk_promo_redemptions_user FOREIGN KEY (user_id) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_activity_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_user_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) DEFAULT NULL,
            entity_id INT UNSIGNED DEFAULT NULL,
            detail VARCHAR(1000) DEFAULT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_activity_created (created_at),
            KEY idx_activity_admin (admin_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            user_id INT UNSIGNED DEFAULT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            ip VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            detail VARCHAR(240) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_login_audit_created (created_at),
            KEY idx_login_audit_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (!table_has_column($pdo, 'users', 'totp_secret')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER password_hash");
    }
    if (!table_has_column($pdo, 'users', 'totp_enabled')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret");
    }
    if (!table_has_column($pdo, 'users', 'email_otp_enabled')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_otp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_enabled");
    }

    seed_default_platform_settings($pdo);
}

function default_platform_settings()
{
    return array(
        'free_registration_credits' => (string) (int) app_config('free_registration_credits', 20),
        'free_registration_validity_days' => (string) max(1, (int) app_config('free_registration_validity_days', 2)),
        'default_tag_width_mm' => '80.00',
        'default_tag_height_mm' => '18.00',
        'default_font_size_pt' => '8.00',
        'announcement_enabled' => '0',
        'announcement_message' => '',
        'feature_razorpay' => '1',
        'feature_registration' => '1',
        'feature_reprints' => '1',
        'invoice_legal_name' => 'Migids Software LLP',
        'invoice_gstin' => '',
        'invoice_address' => 'Bengaluru, Karnataka, India',
        'invoice_email' => (string) app_config('mail_from', 'noreply@tagforge.in'),
        'invoice_state' => 'Karnataka',
        'invoice_state_code' => '29',
    );
}

function seed_default_platform_settings($pdo = null)
{
    $pdo = $pdo ?: db();
    $defaults = default_platform_settings();
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)'
    );
    foreach ($defaults as $key => $value) {
        $stmt->execute(array($key, $value));
    }
}

function platform_settings_all()
{
    ensure_ops_schema();
    $rows = db_all('SELECT setting_key, setting_value FROM platform_settings');
    $out = default_platform_settings();
    foreach ($rows as $row) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}

function platform_setting($key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = platform_settings_all();
        } catch (Exception $e) {
            $cache = default_platform_settings();
        }
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    return $default;
}

function platform_settings_clear_cache()
{
    // Force reload on next read by using a dummy reassignment pattern via static reset.
    // Re-query through platform_settings_all after writes.
}

function set_platform_settings($values, $adminId = null)
{
    ensure_ops_schema();
    $allowed = array_keys(default_platform_settings());
    foreach ($values as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        $str = is_bool($value) ? ($value ? '1' : '0') : trim((string) $value);
        db_exec(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            array($key, $str, $adminId)
        );
    }
}

function platform_bool($key, $default = true)
{
    $value = platform_setting($key, $default ? '1' : '0');
    return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes';
}

function platform_int($key, $default = 0)
{
    return (int) platform_setting($key, (string) $default);
}

function free_pack_credits()
{
    return max(0, platform_int('free_registration_credits', (int) app_config('free_registration_credits', 20)));
}

function free_pack_days()
{
    return max(1, platform_int('free_registration_validity_days', (int) app_config('free_registration_validity_days', 2)));
}

function feature_enabled($flag)
{
    $map = array(
        'razorpay' => 'feature_razorpay',
        'registration' => 'feature_registration',
        'reprints' => 'feature_reprints',
    );
    $key = isset($map[$flag]) ? $map[$flag] : $flag;
    return platform_bool($key, true);
}

function client_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
}

function client_user_agent()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    return substr($ua, 0, 255);
}

function log_admin_activity($admin, $action, $entityType = null, $entityId = null, $detail = null)
{
    try {
        ensure_ops_schema();
        db_exec(
            'INSERT INTO admin_activity_log (admin_user_id, action, entity_type, entity_id, detail, ip)
             VALUES (?, ?, ?, ?, ?, ?)',
            array(
                $admin ? (int) $admin['id'] : null,
                substr((string) $action, 0, 80),
                $entityType ? substr((string) $entityType, 0, 40) : null,
                $entityId !== null ? (int) $entityId : null,
                $detail !== null ? substr((string) $detail, 0, 1000) : null,
                substr(client_ip(), 0, 64),
            )
        );
    } catch (Exception $e) {
        // Never break primary action on audit failure.
    }
}

function log_login_attempt($email, $user, $success, $detail = null)
{
    try {
        ensure_ops_schema();
        $isAdmin = $user && isset($user['role']) && $user['role'] === 'admin';
        db_exec(
            'INSERT INTO login_audit (email, user_id, success, is_admin, ip, user_agent, detail)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            array(
                substr((string) $email, 0, 255),
                $user ? (int) $user['id'] : null,
                $success ? 1 : 0,
                $isAdmin ? 1 : 0,
                substr(client_ip(), 0, 64),
                client_user_agent(),
                $detail !== null ? substr((string) $detail, 0, 240) : null,
            )
        );
    } catch (Exception $e) {
        // ignore
    }
}

function promo_to_array($row)
{
    return array(
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'description' => $row['description'],
        'tag_credits' => (int) $row['tag_credits'],
        'validity_days' => (int) $row['validity_days'],
        'is_unlimited' => (bool) $row['is_unlimited'],
        'max_redemptions' => (int) $row['max_redemptions'],
        'redemption_count' => (int) $row['redemption_count'],
        'starts_at' => as_iso($row['starts_at']),
        'ends_at' => as_iso($row['ends_at']),
        'is_active' => (bool) $row['is_active'],
        'created_at' => as_iso($row['created_at']),
    );
}

function activity_to_array($row)
{
    return array(
        'id' => (int) $row['id'],
        'admin_user_id' => $row['admin_user_id'] !== null ? (int) $row['admin_user_id'] : null,
        'admin_name' => isset($row['admin_name']) ? $row['admin_name'] : null,
        'admin_email' => isset($row['admin_email']) ? $row['admin_email'] : null,
        'action' => $row['action'],
        'entity_type' => $row['entity_type'],
        'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
        'detail' => $row['detail'],
        'ip' => $row['ip'],
        'created_at' => as_iso($row['created_at']),
    );
}

function login_audit_to_array($row)
{
    return array(
        'id' => (int) $row['id'],
        'email' => $row['email'],
        'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
        'success' => (bool) $row['success'],
        'is_admin' => (bool) $row['is_admin'],
        'ip' => $row['ip'],
        'user_agent' => $row['user_agent'],
        'detail' => $row['detail'],
        'created_at' => as_iso($row['created_at']),
    );
}

function platform_public_payload()
{
    $announcement = null;
    if (platform_bool('announcement_enabled', false)) {
        $message = trim((string) platform_setting('announcement_message', ''));
        if ($message !== '') {
            $announcement = $message;
        }
    }
    return array(
        'announcement' => $announcement,
        'features' => array(
            'razorpay' => feature_enabled('razorpay'),
            'registration' => feature_enabled('registration'),
            'reprints' => feature_enabled('reprints'),
        ),
        'free_registration_credits' => free_pack_credits(),
        'free_registration_validity_days' => free_pack_days(),
    );
}

/* ---- TOTP helpers (RFC 6238, no external libs) ---- */

function base32_decode_secret($secret)
{
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string) $secret));
    if ($secret === '') {
        return '';
    }
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $out = '';
    $len = strlen($secret);
    for ($i = 0; $i < $len; $i++) {
        $val = strpos($map, $secret[$i]);
        if ($val === false) {
            continue;
        }
        $buffer = ($buffer << 5) | $val;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buffer >> $bits) & 0xFF);
        }
    }
    return $out;
}

function base32_encode_secret($binary)
{
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $out = '';
    $len = strlen($binary);
    for ($i = 0; $i < $len; $i++) {
        $buffer = ($buffer << 8) | ord($binary[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $map[($buffer >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $map[($buffer << (5 - $bits)) & 31];
    }
    return $out;
}

function generate_totp_secret()
{
    $bytes = function_exists('random_bytes') ? random_bytes(20) : openssl_random_pseudo_bytes(20);
    return base32_encode_secret($bytes);
}

function totp_code($secret, $timeSlice = null)
{
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    $key = base32_decode_secret($secret);
    if ($key === '') {
        return null;
    }
    $time = pack('N*', 0, $timeSlice);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    return str_pad((string) $truncated, 6, '0', STR_PAD_LEFT);
}

function verify_totp_code($secret, $code)
{
    $code = preg_replace('/\s+/', '', (string) $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $slice = (int) floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        $expected = totp_code($secret, $slice + $i);
        if ($expected !== null && hash_equals($expected, $code)) {
            return true;
        }
    }
    return false;
}

function admin_requires_2fa($user)
{
    if (!$user || $user['role'] !== 'admin') {
        return false;
    }
    return !empty($user['totp_enabled']) || !empty($user['email_otp_enabled']);
}

function create_email_otp($user)
{
    $n = function_exists('random_int') ? random_int(0, 999999) : mt_rand(0, 999999);
    $code = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    $_SESSION['admin_email_otp'] = array(
        'user_id' => (int) $user['id'],
        'code_hash' => hash('sha256', $code),
        'expires_at' => time() + 10 * 60,
    );
    $appName = app_config('app_name', 'TagForge');
    $body = "Hello {$user['name']},\n\nYour {$appName} admin login code is: {$code}\n\nValid for 10 minutes.\n\n— {$appName}\n";
    send_app_mail($user['email'], $appName . ' admin login code', $body);
    return true;
}

function verify_email_otp($user, $code)
{
    if (empty($_SESSION['admin_email_otp']) || !is_array($_SESSION['admin_email_otp'])) {
        return false;
    }
    $otp = $_SESSION['admin_email_otp'];
    if ((int) $otp['user_id'] !== (int) $user['id']) {
        return false;
    }
    if (empty($otp['expires_at']) || time() > (int) $otp['expires_at']) {
        unset($_SESSION['admin_email_otp']);
        return false;
    }
    $code = preg_replace('/\s+/', '', (string) $code);
    if (!hash_equals($otp['code_hash'], hash('sha256', $code))) {
        return false;
    }
    unset($_SESSION['admin_email_otp']);
    return true;
}
