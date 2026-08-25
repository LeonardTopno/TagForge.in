<?php

require_once __DIR__ . '/includes/init.php';

header('X-Content-Type-Options: nosniff');
apply_cors_headers();

$method = request_method();
$route = request_route();

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    dispatch_api($method, $route);
} catch (PDOException $e) {
    json_error(500, 'Database error. Check the MySQL connection and that install.php has been run.');
} catch (Exception $e) {
    json_error(500, $e->getMessage());
}

function dispatch_api($method, $route)
{
    if ($route === 'health' && $method === 'GET') {
        db()->query('SELECT 1');
        json_ok(array('status' => 'ok'));
    }

    if ($route === 'version' && $method === 'GET') {
        json_ok(array(
            'app' => 'TagForge',
            'deploy' => '2026-08-26-auth-csrf-fix',
        ));
    }

    if ($route === 'csrf' && $method === 'GET') {
        json_ok(array('csrf_token' => csrf_token()));
    }

    // Anonymous auth endpoints: CSRF depends on a prior session cookie, which is unreliable
    // across first visits / subdomain cookie migrations on shared hosting.
    $csrfExempt = array(
        'auth/login' => true,
        'auth/register' => true,
        'auth/forgot-password' => true,
        'auth/reset-password' => true,
    );
    if ($method === 'POST' && isset($csrfExempt[$route])) {
        // fall through without require_csrf()
    } else {
        require_csrf();
    }

    if ($route === 'auth/register' && $method === 'POST') {
        handle_register();
    }
    if ($route === 'auth/login' && $method === 'POST') {
        handle_login();
    }
    if ($route === 'auth/forgot-password' && $method === 'POST') {
        handle_forgot_password();
    }
    if ($route === 'auth/reset-password' && $method === 'POST') {
        handle_reset_password();
    }
    if ($route === 'auth/logout' && $method === 'POST') {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        json_ok(array('ok' => true));
    }
    if ($route === 'me' && $method === 'GET') {
        $user = require_user();
        json_ok(auth_payload($user, get_shop($user['shop_id'])));
    }
    if ($route === 'dashboard' && $method === 'GET') {
        handle_dashboard(require_user());
    }
    if ($route === 'tags' && $method === 'GET') {
        handle_list_tags(require_user());
    }
    if ($route === 'tags' && $method === 'POST') {
        handle_create_tag(require_user());
    }
    if ($route === 'tags/print' && $method === 'POST') {
        handle_print_tag(require_user());
    }
    if ($route === 'settings' && $method === 'GET') {
        json_ok(shop_to_array(get_shop(require_user()['shop_id'])));
    }
    if ($route === 'settings' && $method === 'PUT') {
        handle_update_settings(require_user());
    }
    if ($route === 'settings/logo' && $method === 'POST') {
        handle_upload_logo(require_user());
    }
    if ($route === 'settings/logo' && $method === 'DELETE') {
        handle_delete_logo(require_user());
    }
    if ($route === 'settings/items' && $method === 'GET') {
        handle_list_items(require_user());
    }
    if ($route === 'settings/items' && $method === 'POST') {
        handle_create_item(require_user());
    }
    if ($route === 'settings/items' && $method === 'PUT') {
        handle_update_item(require_user());
    }
    if ($route === 'settings/items' && $method === 'DELETE') {
        handle_delete_item(require_user());
    }
    if ($route === 'billing/summary' && $method === 'GET') {
        json_ok(billing_summary(get_shop(require_user()['shop_id'])));
    }
    if ($route === 'billing/plans' && $method === 'GET') {
        $rows = db_all('SELECT * FROM billing_plans WHERE is_active = 1 ORDER BY sort_order ASC, price_inr ASC');
        json_ok(array_map('plan_to_array', $rows));
    }
    if ($route === 'billing/ledger' && $method === 'GET') {
        $user = require_user();
        $rows = db_all(
            'SELECT * FROM credit_ledger_entries WHERE shop_id = ? ORDER BY created_at DESC, id DESC LIMIT 50',
            array($user['shop_id'])
        );
        json_ok(array_map('ledger_to_array', $rows));
    }
    if ($route === 'billing/purchases' && $method === 'POST') {
        handle_create_purchase(require_user());
    }
    if ($route === 'billing/purchases/confirm' && $method === 'POST') {
        handle_confirm_purchase(require_user());
    }
    if ($route === 'admin/plans' && $method === 'GET') {
        require_admin();
        $rows = db_all('SELECT * FROM billing_plans ORDER BY sort_order ASC, price_inr ASC');
        json_ok(array_map('plan_to_array', $rows));
    }
    if ($route === 'admin/plans' && $method === 'PUT') {
        handle_admin_update_plan(require_admin());
    }
    if ($route === 'admin/tenants' && $method === 'GET') {
        require_admin();
        $shops = db_all('SELECT * FROM shops ORDER BY created_at DESC');
        $out = array();
        foreach ($shops as $shop) {
            $out[] = admin_tenant_response($shop);
        }
        json_ok($out);
    }
    if ($route === 'admin/tenants/ledger' && $method === 'GET') {
        require_admin();
        $shopId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $shop = get_shop($shopId);
        $rows = db_all(
            'SELECT * FROM credit_ledger_entries WHERE shop_id = ? ORDER BY created_at DESC, id DESC LIMIT 100',
            array($shop['id'])
        );
        json_ok(array_map('ledger_to_array', $rows));
    }
    if ($route === 'admin/tenants/credit-adjustments' && $method === 'POST') {
        handle_admin_adjust_credits(require_admin());
    }

    json_error(404, 'Unknown API route');
}

function handle_register()
{
    $data = request_json();
    $shopName = require_string($data, 'shop_name', 2, 160);
    $shortName = require_string($data, 'shop_short_name', 2, 40);
    $ownerName = require_string($data, 'owner_name', 2, 120);
    $email = require_email($data);
    $password = require_string($data, 'password', 8, 128, 'password');

    if (db_one('SELECT id FROM users WHERE email = ?', array($email))) {
        json_error(409, 'Email is already registered');
    }

    $credits = (int) app_config('free_registration_credits', 20);
    $freeDays = max(1, (int) app_config('free_registration_validity_days', 2));
    $creditsExpireAt = gmdate('Y-m-d H:i:s', time() + ($freeDays * 86400));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_exec(
            'INSERT INTO shops (name, short_name, tag_credit_balance, credits_expire_at) VALUES (?, ?, ?, ?)',
            array($shopName, $shortName, $credits, $creditsExpireAt)
        );
        $shopId = (int) $pdo->lastInsertId();
        db_exec(
            'INSERT INTO users (shop_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            array($shopId, $ownerName, $email, password_hash($password, PASSWORD_DEFAULT), 'owner')
        );
        $userId = (int) $pdo->lastInsertId();
        seed_items_for_shop($pdo, $shopId);
        add_ledger_entry(
            $shopId,
            'credit',
            $credits,
            $credits,
            'Free starter pack: ' . $credits . ' tags valid for ' . $freeDays . ' days'
        );
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', array($userId));
    $shop = get_shop($shopId);
    login_user($user);
    json_ok(auth_payload($user, $shop), 201);
}

function handle_login()
{
    $data = request_json();
    $email = require_email($data);
    $password = isset($data['password']) ? (string) $data['password'] : '';
    $user = db_one('SELECT * FROM users WHERE email = ?', array($email));
    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_error(401, 'Invalid email or password');
    }
    if (empty($user['is_active'])) {
        json_error(401, 'Inactive user');
    }
    login_user($user);
    json_ok(auth_payload($user, get_shop($user['shop_id'])));
}

function handle_forgot_password()
{
    ensure_password_reset_schema();
    $data = request_json();
    $email = require_email($data);
    $generic = 'If that email is registered, a password reset link has been sent.';
    $user = db_one('SELECT * FROM users WHERE email = ? AND is_active = 1', array($email));

    $response = array('ok' => true, 'detail' => $generic);
    if (!$user) {
        json_ok($response);
    }

    $token = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + 60 * 60);

    db_exec('UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL', array(now_utc(), (int) $user['id']));
    db_exec(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        array((int) $user['id'], $tokenHash, $expiresAt)
    );

    $resetUrl = password_reset_url($token);
    $appName = app_config('app_name', 'Jewellery Tag Printer');
    $body = "Hello {$user['name']},\n\n"
        . "We received a request to reset your password for {$appName}.\n\n"
        . "Open this link to choose a new password (valid for 1 hour):\n"
        . "{$resetUrl}\n\n"
        . "If you did not request this, you can ignore this email.\n\n"
        . "— {$appName}\n";

    send_app_mail($user['email'], $appName . ' password reset', $body);

    if (app_config('mail_debug', false)) {
        $response['reset_url'] = $resetUrl;
        $response['detail'] = $generic . ' Local testing: open the reset link below.';
    }

    json_ok($response);
}

function handle_reset_password()
{
    ensure_password_reset_schema();
    $data = request_json();
    $token = isset($data['token']) ? trim((string) $data['token']) : '';
    $password = require_string($data, 'password', 8, 128, 'password');

    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        json_error(422, 'Invalid or expired reset link. Request a new one.');
    }

    $tokenHash = hash('sha256', $token);
    $row = db_one(
        'SELECT * FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL LIMIT 1',
        array($tokenHash)
    );
    if (!$row) {
        json_error(422, 'Invalid or expired reset link. Request a new one.');
    }
    if (strtotime($row['expires_at'] . ' UTC') < time()) {
        json_error(422, 'This reset link has expired. Request a new one.');
    }

    $user = db_one('SELECT * FROM users WHERE id = ? AND is_active = 1', array((int) $row['user_id']));
    if (!$user) {
        json_error(422, 'Invalid or expired reset link. Request a new one.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_exec(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            array(password_hash($password, PASSWORD_DEFAULT), (int) $user['id'])
        );
        db_exec(
            'UPDATE password_reset_tokens SET used_at = ? WHERE id = ?',
            array(now_utc(), (int) $row['id'])
        );
        db_exec(
            'UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL AND id <> ?',
            array(now_utc(), (int) $user['id'], (int) $row['id'])
        );
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_ok(array(
        'ok' => true,
        'detail' => 'Password updated. You can sign in with your new password.',
    ));
}

function handle_dashboard($user)
{
    $shopId = $user['shop_id'];
    $totalTags = (int) db_one('SELECT COUNT(*) AS c FROM jewellery_tags WHERE shop_id = ?', array($shopId))['c'];
    $todayTags = (int) db_one(
        'SELECT COUNT(*) AS c FROM jewellery_tags WHERE shop_id = ? AND created_at >= UTC_DATE() AND created_at < UTC_DATE() + INTERVAL 1 DAY',
        array($shopId)
    )['c'];
    $totalPrints = (int) db_one('SELECT COALESCE(SUM(print_count), 0) AS c FROM jewellery_tags WHERE shop_id = ?', array($shopId))['c'];
    $recent = db_all(
        'SELECT * FROM jewellery_tags WHERE shop_id = ? ORDER BY created_at DESC, id DESC LIMIT 5',
        array($shopId)
    );
    json_ok(array(
        'total_tags' => $totalTags,
        'today_tags' => $todayTags,
        'total_prints' => $totalPrints,
        'recent_tags' => array_map('tag_to_array', $recent),
        'billing' => billing_summary(get_shop($shopId)),
    ));
}

function handle_list_tags($user)
{
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sql = 'SELECT * FROM jewellery_tags WHERE shop_id = ?';
    $params = array($user['shop_id']);
    if ($search !== '') {
        $sql .= ' AND (tag_number LIKE ? OR item_name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 100';
    json_ok(array_map('tag_to_array', db_all($sql, $params)));
}

function handle_create_tag($user)
{
    $data = request_json();
    $itemName = require_string($data, 'item_name', 1, 100);
    $category = optional_string($data, 'category', 60);
    $purity = optional_string($data, 'purity', 30);
    $pieces = isset($data['pieces']) ? require_int($data, 'pieces', 1, 999) : 1;
    $gross = require_decimal($data, 'gross_weight', 0, 999999, 3);
    $stone = isset($data['stone_weight']) ? require_decimal($data, 'stone_weight', 0, 999999, 3) : '0.000';
    $other = isset($data['other_deduction']) ? require_decimal($data, 'other_deduction', 0, 999999, 3) : '0.000';
    $copies = isset($data['copies']) ? require_int($data, 'copies', 1, 99) : 1;
    $net = round((float) $gross - (float) $stone - (float) $other, 3);
    if ($net < 0) {
        json_error(422, 'Net weight cannot be negative');
    }
    $netFormatted = format_weight($net);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $shop = get_shop($user['shop_id'], true);
        $tagNumber = next_tag_number($shop);
        db_exec(
            'INSERT INTO jewellery_tags
                (shop_id, created_by, tag_number, item_name, category, purity, pieces, gross_weight, stone_weight, other_deduction, net_weight, copies)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $user['shop_id'], $user['id'], $tagNumber, $itemName,
                $category === '' ? null : $category,
                $purity === '' ? null : $purity,
                $pieces, $gross, $stone, $other, $netFormatted, $copies,
            )
        );
        $tagId = (int) $pdo->lastInsertId();
        db_exec('UPDATE shops SET next_tag_number = next_tag_number + 1 WHERE id = ?', array($shop['id']));
        charge_tag_credits($shop, 1, 'Created tag ' . $tagNumber, $tagId);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_ok(tag_to_array(db_one('SELECT * FROM jewellery_tags WHERE id = ?', array($tagId))), 201);
}

function handle_print_tag($user)
{
    $data = request_json();
    $tagId = isset($data['id']) ? (int) $data['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $tag = db_one('SELECT * FROM jewellery_tags WHERE id = ? AND shop_id = ?', array($tagId, $user['shop_id']));
    if (!$tag) {
        json_error(404, 'Tag not found');
    }
    db_exec('UPDATE jewellery_tags SET print_count = print_count + copies WHERE id = ?', array($tag['id']));
    db_exec(
        'INSERT INTO print_logs (shop_id, jewellery_tag_id, printed_by, copies, print_status) VALUES (?, ?, ?, ?, ?)',
        array($user['shop_id'], $tag['id'], $user['id'], $tag['copies'], 'printed')
    );
    json_ok(tag_to_array(db_one('SELECT * FROM jewellery_tags WHERE id = ?', array($tag['id']))));
}

function handle_update_settings($user)
{
    $data = request_json();
    $name = require_string($data, 'name', 2, 160);
    $address = optional_string($data, 'address', 255);
    $phone = optional_string($data, 'phone_number', 20);
    $gst = optional_string($data, 'gst_no', 20);
    $shortName = require_string($data, 'short_name', 2, 40);
    $prefix = strtoupper(require_string($data, 'tag_prefix', 1, 12));
    $width = require_decimal($data, 'tag_width_mm', 20.01, 100, 2);
    $height = require_decimal($data, 'tag_height_mm', 8.01, 50, 2);
    $font = require_decimal($data, 'font_size_pt', 4.01, 14, 2);
    $xOff = require_decimal($data, 'horizontal_offset_mm', -10, 10, 2);
    $yOff = require_decimal($data, 'vertical_offset_mm', -10, 10, 2);
    $showShop = require_bool($data, 'show_shop_name') ? 1 : 0;

    db_exec(
        'UPDATE shops SET name = ?, address = ?, phone_number = ?, gst_no = ?, short_name = ?, tag_prefix = ?,
            tag_width_mm = ?, tag_height_mm = ?, font_size_pt = ?, horizontal_offset_mm = ?, vertical_offset_mm = ?, show_shop_name = ?
         WHERE id = ?',
        array($name, $address, $phone, $gst, $shortName, $prefix, $width, $height, $font, $xOff, $yOff, $showShop, $user['shop_id'])
    );
    json_ok(shop_to_array(get_shop($user['shop_id'])));
}

function handle_upload_logo($user)
{
    $shop = get_shop($user['shop_id']);
    if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        json_error(422, 'Logo file is empty');
    }
    $file = $_FILES['file'];
    if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        json_error(422, 'Could not upload logo');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        json_error(413, 'Logo must be 5 MB or smaller');
    }
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : (isset($file['type']) ? $file['type'] : '');
    if ($finfo) {
        finfo_close($finfo);
    }
    $map = array(
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp',
        'image/gif' => '.gif',
    );
    $mime = strtolower($mime);
    if (!isset($map[$mime])) {
        json_error(422, 'Logo must be a JPG, PNG, WEBP, or GIF image');
    }
    ensure_upload_dirs();
    delete_shop_logo_file($shop);
    $relative = 'logos/' . $shop['id'] . $map[$mime];
    $dest = upload_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_error(500, 'Could not save logo');
    }
    db_exec('UPDATE shops SET logo_path = ? WHERE id = ?', array($relative, $shop['id']));
    json_ok(shop_to_array(get_shop($shop['id'])));
}

function handle_delete_logo($user)
{
    $shop = get_shop($user['shop_id']);
    delete_shop_logo_file($shop);
    db_exec('UPDATE shops SET logo_path = NULL WHERE id = ?', array($shop['id']));
    json_ok(shop_to_array(get_shop($shop['id'])));
}

function handle_list_items($user)
{
    $rows = db_all('SELECT * FROM shop_items WHERE shop_id = ? ORDER BY LOWER(name) ASC', array($user['shop_id']));
    $out = array();
    foreach ($rows as $row) {
        $out[] = array(
            'id' => (int) $row['id'],
            'shop_id' => (int) $row['shop_id'],
            'name' => $row['name'],
            'sort_order' => (int) $row['sort_order'],
        );
    }
    json_ok($out);
}

function ensure_unique_item_name($shopId, $name, $itemId = null)
{
    $normalized = normalize_item_name($name);
    if ($normalized === '') {
        json_error(422, 'Item name is required');
    }
    $sql = 'SELECT id FROM shop_items WHERE shop_id = ? AND LOWER(name) = LOWER(?)';
    $params = array($shopId, $normalized);
    if ($itemId) {
        $sql .= ' AND id <> ?';
        $params[] = $itemId;
    }
    if (db_one($sql, $params)) {
        json_error(409, 'That item is already in the list');
    }
    return $normalized;
}

function get_shop_item($shopId, $itemId)
{
    $item = db_one('SELECT * FROM shop_items WHERE id = ? AND shop_id = ?', array($itemId, $shopId));
    if (!$item) {
        json_error(404, 'Item not found');
    }
    return $item;
}

function item_to_array($item)
{
    return array(
        'id' => (int) $item['id'],
        'shop_id' => (int) $item['shop_id'],
        'name' => $item['name'],
        'sort_order' => (int) $item['sort_order'],
    );
}

function handle_create_item($user)
{
    $data = request_json();
    $name = ensure_unique_item_name($user['shop_id'], isset($data['name']) ? $data['name'] : '');
    $max = db_one('SELECT COALESCE(MAX(sort_order), -1) AS m FROM shop_items WHERE shop_id = ?', array($user['shop_id']));
    db_exec(
        'INSERT INTO shop_items (shop_id, name, sort_order) VALUES (?, ?, ?)',
        array($user['shop_id'], $name, ((int) $max['m']) + 1)
    );
    $itemId = (int) db()->lastInsertId();
    json_ok(item_to_array(db_one('SELECT * FROM shop_items WHERE id = ?', array($itemId))), 201);
}

function handle_update_item($user)
{
    $data = request_json();
    $itemId = isset($data['id']) ? (int) $data['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $item = get_shop_item($user['shop_id'], $itemId);
    $name = ensure_unique_item_name($user['shop_id'], isset($data['name']) ? $data['name'] : '', $item['id']);
    db_exec('UPDATE shop_items SET name = ? WHERE id = ?', array($name, $item['id']));
    json_ok(item_to_array(db_one('SELECT * FROM shop_items WHERE id = ?', array($item['id']))));
}

function handle_delete_item($user)
{
    $data = request_json();
    $itemId = isset($data['id']) ? (int) $data['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $item = get_shop_item($user['shop_id'], $itemId);
    db_exec('DELETE FROM shop_items WHERE id = ?', array($item['id']));
    http_response_code(204);
    exit;
}

function handle_create_purchase($user)
{
    $data = request_json();
    $planId = require_int($data, 'plan_id', 1, 1000000);
    $months = require_int($data, 'months', 1, 24);
    $plan = db_one('SELECT * FROM billing_plans WHERE id = ? AND is_active = 1', array($planId));
    if (!$plan) {
        json_error(404, 'Plan not found');
    }
    if (empty($plan['is_unlimited'])) {
        json_error(422, 'Only the Monthly Unlimited plan can be purchased.');
    }

    $unitPrice = (int) app_config('monthly_plan_price_inr', (int) $plan['price_inr']);
    if ($unitPrice < 1) {
        $unitPrice = (int) $plan['price_inr'];
    }
    $amountInr = $unitPrice * $months;
    $validityDays = months_to_validity_days($months);
    $notes = 'months=' . $months . '; unit_price=' . $unitPrice;

    $orderId = null;
    $checkoutMode = 'local';
    if (razorpay_configured()) {
        try {
            $order = razorpay_create_order(
                $amountInr,
                'shop' . $user['shop_id'] . '_p' . time(),
                array(
                    'shop_id' => (string) $user['shop_id'],
                    'plan_id' => (string) $plan['id'],
                    'months' => (string) $months,
                )
            );
            $orderId = isset($order['id']) ? $order['id'] : null;
            $checkoutMode = 'razorpay';
        } catch (Exception $e) {
            json_error(502, $e->getMessage());
        }
    } else {
        $orderId = 'local_order_' . $user['shop_id'] . '_' . time();
        $notes .= '; local_checkout=1';
    }

    db_exec(
        'INSERT INTO plan_purchases (shop_id, plan_id, amount_inr, tag_credits, validity_days, is_unlimited, razorpay_order_id, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        array(
            $user['shop_id'],
            $plan['id'],
            $amountInr,
            0,
            $validityDays,
            1,
            $orderId,
            $notes,
        )
    );
    $purchaseId = (int) db()->lastInsertId();
    $purchase = db_one('SELECT * FROM plan_purchases WHERE id = ?', array($purchaseId));
    $payload = purchase_to_array($purchase);
    $payload['checkout_mode'] = $checkoutMode;
    $payload['razorpay_key_id'] = $checkoutMode === 'razorpay' ? app_config('razorpay_key_id', '') : null;
    $payload['amount_paise'] = $amountInr * 100;
    $payload['currency'] = 'INR';
    $payload['unit_price_inr'] = $unitPrice;
    json_ok($payload, 201);
}

function handle_confirm_purchase($user)
{
    $data = request_json();
    $purchaseId = isset($data['id']) ? (int) $data['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $paymentId = isset($data['razorpay_payment_id']) ? trim((string) $data['razorpay_payment_id']) : '';
    $orderId = isset($data['razorpay_order_id']) ? trim((string) $data['razorpay_order_id']) : '';
    $signature = isset($data['razorpay_signature']) ? trim((string) $data['razorpay_signature']) : '';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $purchase = db_one('SELECT * FROM plan_purchases WHERE id = ? AND shop_id = ? FOR UPDATE', array($purchaseId, $user['shop_id']));
        if (!$purchase) {
            json_error(404, 'Purchase not found');
        }
        $shop = get_shop($user['shop_id'], true);
        if ($purchase['status'] === 'paid') {
            $pdo->commit();
            json_ok(billing_summary($shop));
        }

        $isLocalOrder = strpos((string) $purchase['razorpay_order_id'], 'local_order_') === 0;
        if (razorpay_configured() && !$isLocalOrder) {
            if ($orderId === '' || $paymentId === '' || $signature === '') {
                json_error(422, 'Razorpay payment details are missing.');
            }
            if ($orderId !== (string) $purchase['razorpay_order_id']) {
                json_error(422, 'Razorpay order mismatch.');
            }
            if (!razorpay_verify_signature($orderId, $paymentId, $signature)) {
                json_error(400, 'Invalid Razorpay payment signature.');
            }
        } else {
            $paymentId = $paymentId !== '' ? $paymentId : ('local_payment_' . $purchase['id']);
        }

        db_exec(
            'UPDATE plan_purchases SET status = ?, razorpay_payment_id = ?, paid_at = ? WHERE id = ?',
            array('paid', $paymentId, now_utc(), $purchase['id'])
        );
        $purchase['status'] = 'paid';
        $purchase['razorpay_payment_id'] = $paymentId;
        grant_purchase_to_shop($shop, $purchase);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    json_ok(billing_summary(get_shop($user['shop_id'])));
}

function handle_admin_update_plan($admin)
{
    $data = request_json();
    $planId = isset($data['id']) ? (int) $data['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $plan = db_one('SELECT * FROM billing_plans WHERE id = ?', array($planId));
    if (!$plan) {
        json_error(404, 'Plan not found');
    }
    $name = require_string($data, 'name', 2, 80);
    $description = require_string($data, 'description', 2, 240);
    $price = require_int($data, 'price_inr', 1, 10000000);
    $credits = require_int($data, 'tag_credits', 0, 10000000);
    $days = require_int($data, 'validity_days', 1, 3660);
    $unlimited = require_bool($data, 'is_unlimited') ? 1 : 0;
    $active = require_bool($data, 'is_active') ? 1 : 0;
    $sort = require_int($data, 'sort_order', 0, 100000);
    db_exec(
        'UPDATE billing_plans SET name = ?, description = ?, price_inr = ?, tag_credits = ?, validity_days = ?, is_unlimited = ?, is_active = ?, sort_order = ? WHERE id = ?',
        array($name, $description, $price, $credits, $days, $unlimited, $active, $sort, $plan['id'])
    );
    json_ok(plan_to_array(db_one('SELECT * FROM billing_plans WHERE id = ?', array($plan['id']))));
}

function handle_admin_adjust_credits($admin)
{
    $data = request_json();
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $note = require_string($data, 'note', 3, 240);
    $delta = isset($data['credits_delta']) ? require_int($data, 'credits_delta', -100000, 100000) : 0;
    $setBalance = optional_int($data, 'set_credit_balance', 0, 1000000);
    $creditDays = optional_int($data, 'credits_validity_days', 1, 3660);
    $unlimitedDays = optional_int($data, 'unlimited_validity_days', 0, 3660);
    $paymentRef = optional_string($data, 'payment_reference', 120);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $shop = get_shop($shopId, true);
        $oldBalance = (int) $shop['tag_credit_balance'];
        if ($setBalance !== null) {
            $shop['tag_credit_balance'] = $setBalance;
        } else {
            $shop['tag_credit_balance'] = max(0, $oldBalance + $delta);
        }
        $creditsChanged = (int) $shop['tag_credit_balance'] - $oldBalance;
        $fields = array('tag_credit_balance = ?');
        $params = array($shop['tag_credit_balance']);
        if ($creditDays !== null) {
            $shop['credits_expire_at'] = gmdate('Y-m-d H:i:s', time() + ($creditDays * 86400));
            $fields[] = 'credits_expire_at = ?';
            $params[] = $shop['credits_expire_at'];
        }
        if ($unlimitedDays !== null) {
            $shop['unlimited_until'] = ($unlimitedDays === 0) ? null : gmdate('Y-m-d H:i:s', time() + ($unlimitedDays * 86400));
            $fields[] = 'unlimited_until = ?';
            $params[] = $shop['unlimited_until'];
        }
        $params[] = $shop['id'];
        db_exec('UPDATE shops SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

        $parts = array($note);
        if ($paymentRef !== '') {
            $parts[] = 'Payment: ' . $paymentRef;
        }
        if ($setBalance !== null) {
            $parts[] = 'Balance set from ' . $oldBalance . ' to ' . $shop['tag_credit_balance'];
        } elseif ($creditsChanged) {
            $parts[] = 'Balance changed by ' . $creditsChanged;
        }
        if ($creditDays !== null) {
            $parts[] = 'Credit validity ' . $creditDays . ' days';
        }
        if ($unlimitedDays !== null) {
            $parts[] = $unlimitedDays === 0 ? 'Unlimited cleared' : ('Unlimited ' . $unlimitedDays . ' days');
        }
        add_ledger_entry(
            $shop['id'],
            $creditsChanged >= 0 ? 'credit' : 'debit',
            abs($creditsChanged),
            $shop['tag_credit_balance'],
            implode(' | ', $parts)
        );
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    json_ok(admin_tenant_response(get_shop($shopId)));
}
