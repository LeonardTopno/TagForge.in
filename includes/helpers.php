<?php

if (!function_exists('app_config')) {
    function app_config($key, $default = null)
    {
        $config = isset($GLOBALS['APP_CONFIG']) ? $GLOBALS['APP_CONFIG'] : array();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }
}

function now_utc()
{
    return gmdate('Y-m-d H:i:s');
}

function json_ok($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function json_error($code, $detail)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('detail' => $detail));
    exit;
}

function request_json()
{
    if (array_key_exists('APP_REQUEST_JSON', $GLOBALS)) {
        return $GLOBALS['APP_REQUEST_JSON'];
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        $GLOBALS['APP_REQUEST_JSON'] = array();
        return $GLOBALS['APP_REQUEST_JSON'];
    }
    $data = json_decode($raw, true);
    $GLOBALS['APP_REQUEST_JSON'] = is_array($data) ? $data : array();
    return $GLOBALS['APP_REQUEST_JSON'];
}

function request_method()
{
    return strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
}

function request_route()
{
    $route = isset($_GET['r']) ? $_GET['r'] : '';
    return trim($route, '/');
}

function str_len($value)
{
    $value = (string) $value;
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function require_string($data, $key, $min, $max, $label = null)
{
    $label = $label ? $label : str_replace('_', ' ', $key);
    $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
    $len = str_len($value);
    if ($len < $min || $len > $max) {
        json_error(422, ucfirst($label) . ' must be between ' . $min . ' and ' . $max . ' characters');
    }
    return $value;
}

function optional_string($data, $key, $max)
{
    $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
    if (str_len($value) > $max) {
        json_error(422, ucfirst(str_replace('_', ' ', $key)) . ' is too long');
    }
    return $value;
}

function require_email($data, $key = 'email')
{
    $email = strtolower(require_string($data, $key, 3, 255, 'email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error(422, 'Enter a valid email address');
    }
    return $email;
}

function require_int($data, $key, $min, $max)
{
    if (!isset($data[$key]) || $data[$key] === '' || !is_numeric($data[$key])) {
        json_error(422, ucfirst(str_replace('_', ' ', $key)) . ' is required');
    }
    $value = (int) $data[$key];
    if ($value < $min || $value > $max) {
        json_error(422, ucfirst(str_replace('_', ' ', $key)) . ' is out of range');
    }
    return $value;
}

function optional_int($data, $key, $min, $max)
{
    if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
        return null;
    }
    return require_int($data, $key, $min, $max);
}

function require_decimal($data, $key, $min, $max, $places = 3)
{
    if (!isset($data[$key]) || $data[$key] === '' || !is_numeric($data[$key])) {
        json_error(422, ucfirst(str_replace('_', ' ', $key)) . ' is required');
    }
    $value = round((float) $data[$key], $places);
    if ($value < $min || $value > $max) {
        json_error(422, ucfirst(str_replace('_', ' ', $key)) . ' is out of range');
    }
    return number_format($value, $places, '.', '');
}

function require_bool($data, $key)
{
    if (!isset($data[$key])) {
        return false;
    }
    $value = $data[$key];
    if (is_bool($value)) {
        return $value;
    }
    return $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
}

function normalize_item_name($name)
{
    return trim(preg_replace('/\s+/', ' ', (string) $name));
}

function format_weight($value)
{
    return number_format((float) $value, 3, '.', '');
}

function as_iso($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    $ts = strtotime($value . ' UTC');
    if ($ts === false) {
        return $value;
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function is_unlimited_active($shop)
{
    if (empty($shop['unlimited_until'])) {
        return false;
    }
    return strtotime($shop['unlimited_until'] . ' UTC') > time();
}

function billing_summary($shop)
{
    ensure_credits_are_current($shop);
    $freeDays = free_pack_days();
    $monthlyPrice = (int) app_config('monthly_plan_price_inr', 599);
    return array(
        'tag_credit_balance' => (int) $shop['tag_credit_balance'],
        'credits_expire_at' => as_iso($shop['credits_expire_at']),
        'unlimited_until' => as_iso($shop['unlimited_until']),
        'is_unlimited_active' => is_unlimited_active($shop),
        'tag_price_inr' => (int) app_config('tag_price_inr', 0),
        'free_registration_credits' => free_pack_credits(),
        'free_registration_validity_days' => $freeDays,
        'monthly_plan_price_inr' => $monthlyPrice,
        'needs_subscription' => !is_unlimited_active($shop) && (int) $shop['tag_credit_balance'] <= 0,
        'features' => array(
            'razorpay' => feature_enabled('razorpay'),
            'registration' => feature_enabled('registration'),
            'reprints' => feature_enabled('reprints'),
        ),
    );
}

function shop_to_array($shop)
{
    $logoUrl = null;
    if (!empty($shop['logo_path'])) {
        $logoUrl = 'uploads/' . ltrim($shop['logo_path'], '/');
    }
    return array(
        'id' => (int) $shop['id'],
        'name' => $shop['name'],
        'address' => isset($shop['address']) ? $shop['address'] : '',
        'phone_number' => isset($shop['phone_number']) ? $shop['phone_number'] : '',
        'gst_no' => isset($shop['gst_no']) ? $shop['gst_no'] : '',
        'short_name' => $shop['short_name'],
        'tag_prefix' => $shop['tag_prefix'],
        'next_tag_number' => (int) $shop['next_tag_number'],
        'tag_width_mm' => (string) $shop['tag_width_mm'],
        'tag_height_mm' => (string) $shop['tag_height_mm'],
        'font_size_pt' => (string) $shop['font_size_pt'],
        'horizontal_offset_mm' => (string) $shop['horizontal_offset_mm'],
        'vertical_offset_mm' => (string) $shop['vertical_offset_mm'],
        'show_shop_name' => (bool) $shop['show_shop_name'],
        'logo_url' => $logoUrl,
        'tag_credit_balance' => (int) $shop['tag_credit_balance'],
        'credits_expire_at' => as_iso($shop['credits_expire_at']),
        'unlimited_until' => as_iso($shop['unlimited_until']),
        'is_active' => !isset($shop['is_active']) || (int) $shop['is_active'] === 1,
        'suspended_reason' => isset($shop['suspended_reason']) ? $shop['suspended_reason'] : null,
        'last_active_at' => isset($shop['last_active_at']) ? as_iso($shop['last_active_at']) : null,
    );
}

function user_to_array($user)
{
    return array(
        'id' => (int) $user['id'],
        'shop_id' => (int) $user['shop_id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    );
}

function tag_to_array($tag)
{
    return array(
        'id' => (int) $tag['id'],
        'shop_id' => (int) $tag['shop_id'],
        'tag_number' => $tag['tag_number'],
        'item_name' => $tag['item_name'],
        'category' => $tag['category'],
        'purity' => $tag['purity'],
        'pieces' => (int) $tag['pieces'],
        'gross_weight' => format_weight($tag['gross_weight']),
        'stone_weight' => format_weight($tag['stone_weight']),
        'other_deduction' => format_weight($tag['other_deduction']),
        'net_weight' => format_weight($tag['net_weight']),
        'copies' => (int) $tag['copies'],
        'status' => $tag['status'],
        'print_count' => (int) $tag['print_count'],
        'created_at' => as_iso($tag['created_at']),
    );
}

function plan_to_array($plan)
{
    return array(
        'id' => (int) $plan['id'],
        'code' => $plan['code'],
        'name' => $plan['name'],
        'description' => $plan['description'],
        'price_inr' => (int) $plan['price_inr'],
        'tag_credits' => (int) $plan['tag_credits'],
        'validity_days' => (int) $plan['validity_days'],
        'is_unlimited' => (bool) $plan['is_unlimited'],
        'is_active' => (bool) $plan['is_active'],
        'is_system' => !empty($plan['is_system']),
        'sort_order' => (int) $plan['sort_order'],
    );
}

function ledger_to_array($entry)
{
    return array(
        'id' => (int) $entry['id'],
        'entry_type' => $entry['entry_type'],
        'credits' => (int) $entry['credits'],
        'balance_after' => (int) $entry['balance_after'],
        'description' => $entry['description'],
        'created_at' => as_iso($entry['created_at']),
    );
}

function purchase_to_array($purchase)
{
    $months = 1;
    if (!empty($purchase['notes']) && preg_match('/months=(\d+)/', $purchase['notes'], $match)) {
        $months = max(1, (int) $match[1]);
    }
    $status = $purchase['status'];
    if (!empty($purchase['refunded_at']) && $status === 'paid') {
        $status = 'refunded';
    }
    return array(
        'id' => (int) $purchase['id'],
        'shop_id' => (int) $purchase['shop_id'],
        'plan_id' => (int) $purchase['plan_id'],
        'amount_inr' => (int) $purchase['amount_inr'],
        'tag_credits' => (int) $purchase['tag_credits'],
        'validity_days' => (int) $purchase['validity_days'],
        'months' => $months,
        'is_unlimited' => (bool) $purchase['is_unlimited'],
        'status' => $status,
        'payment_method' => isset($purchase['payment_method']) ? $purchase['payment_method'] : 'razorpay',
        'receipt_note' => isset($purchase['receipt_note']) ? $purchase['receipt_note'] : null,
        'razorpay_order_id' => $purchase['razorpay_order_id'],
        'razorpay_payment_id' => $purchase['razorpay_payment_id'],
        'notes' => isset($purchase['notes']) ? $purchase['notes'] : null,
        'created_at' => as_iso($purchase['created_at']),
        'paid_at' => as_iso($purchase['paid_at']),
        'refunded_at' => isset($purchase['refunded_at']) ? as_iso($purchase['refunded_at']) : null,
        'recorded_by' => isset($purchase['recorded_by']) && $purchase['recorded_by'] !== null ? (int) $purchase['recorded_by'] : null,
    );
}

function next_tag_number($shop)
{
    return $shop['tag_prefix'] . str_pad((string) $shop['next_tag_number'], 6, '0', STR_PAD_LEFT);
}

function upload_root()
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
}

function ensure_upload_dirs()
{
    $logoDir = upload_root() . DIRECTORY_SEPARATOR . 'logos';
    if (!is_dir($logoDir)) {
        mkdir($logoDir, 0755, true);
    }
}

function delete_shop_logo_file($shop)
{
    if (empty($shop['logo_path'])) {
        return;
    }
    $path = upload_root() . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $shop['logo_path']);
    if (is_file($path)) {
        unlink($path);
    }
}

function add_ledger_entry($shopId, $type, $credits, $balanceAfter, $description, $tagId = null, $purchaseId = null)
{
    db_exec(
        'INSERT INTO credit_ledger_entries (shop_id, entry_type, credits, balance_after, description, jewellery_tag_id, purchase_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        array($shopId, $type, $credits, $balanceAfter, $description, $tagId, $purchaseId)
    );
}

function ensure_credits_are_current(&$shop)
{
    if (!empty($shop['credits_expire_at']) && strtotime($shop['credits_expire_at'] . ' UTC') <= time()) {
        $shop['tag_credit_balance'] = 0;
        db_exec('UPDATE shops SET tag_credit_balance = 0 WHERE id = ?', array($shop['id']));
    }
}

function charge_tag_credits(&$shop, $credits, $description, $tagId = null)
{
    if ($credits <= 0 || is_unlimited_active($shop)) {
        return;
    }
    ensure_credits_are_current($shop);
    if ((int) $shop['tag_credit_balance'] < $credits) {
        json_error(
            402,
            'Free tags used up or expired. Buy the Monthly Unlimited plan (Rs. 599/month) from Credits to continue.'
        );
    }
    $shop['tag_credit_balance'] = (int) $shop['tag_credit_balance'] - $credits;
    db_exec('UPDATE shops SET tag_credit_balance = ? WHERE id = ?', array($shop['tag_credit_balance'], $shop['id']));
    add_ledger_entry($shop['id'], 'debit', $credits, $shop['tag_credit_balance'], $description, $tagId, null);
}

function months_to_validity_days($months)
{
    $months = max(1, (int) $months);
    $start = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $end = $start->modify('+' . $months . ' months');
    $days = (int) $start->diff($end)->days;
    return max(1, $days);
}

function grant_purchase_to_shop(&$shop, $purchase)
{
    $days = max(1, (int) $purchase['validity_days']);
    if (!empty($purchase['is_unlimited'])) {
        $baseTs = time();
        if (!empty($shop['unlimited_until'])) {
            $existingTs = strtotime($shop['unlimited_until'] . ' UTC');
            if ($existingTs !== false && $existingTs > $baseTs) {
                $baseTs = $existingTs;
            }
        }
        $shop['unlimited_until'] = gmdate('Y-m-d H:i:s', $baseTs + ($days * 86400));
        db_exec('UPDATE shops SET unlimited_until = ? WHERE id = ?', array($shop['unlimited_until'], $shop['id']));
        add_ledger_entry(
            $shop['id'],
            'credit',
            0,
            (int) $shop['tag_credit_balance'],
            'Unlimited plan activated until ' . $shop['unlimited_until'] . ' UTC',
            null,
            $purchase['id']
        );
        return;
    }
    ensure_credits_are_current($shop);
    $paidUntil = gmdate('Y-m-d H:i:s', time() + ($days * 86400));
    $shop['tag_credit_balance'] = (int) $shop['tag_credit_balance'] + (int) $purchase['tag_credits'];
    $existingExpiry = !empty($shop['credits_expire_at']) ? $shop['credits_expire_at'] : $paidUntil;
    $shop['credits_expire_at'] = ($existingExpiry > $paidUntil) ? $existingExpiry : $paidUntil;
    db_exec(
        'UPDATE shops SET tag_credit_balance = ?, credits_expire_at = ? WHERE id = ?',
        array($shop['tag_credit_balance'], $shop['credits_expire_at'], $shop['id'])
    );
    add_ledger_entry(
        $shop['id'],
        'credit',
        (int) $purchase['tag_credits'],
        $shop['tag_credit_balance'],
        $purchase['tag_credits'] . ' credits purchased',
        null,
        $purchase['id']
    );
}

function owner_for_shop($shopId)
{
    return db_one('SELECT * FROM users WHERE shop_id = ? AND role = ? ORDER BY id ASC LIMIT 1', array($shopId, 'owner'));
}

function admin_tenant_response($shop)
{
    $owner = owner_for_shop($shop['id']);
    $lastTag = db_one(
        'SELECT created_at FROM jewellery_tags WHERE shop_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
        array($shop['id'])
    );
    $creditsExpireAt = as_utc_ts($shop['credits_expire_at']);
    $creditsExpired = $creditsExpireAt !== null && $creditsExpireAt <= time() && !is_unlimited_active($shop);
    return array(
        'id' => (int) $shop['id'],
        'name' => $shop['name'],
        'short_name' => $shop['short_name'],
        'tag_prefix' => $shop['tag_prefix'],
        'address' => isset($shop['address']) ? $shop['address'] : '',
        'phone_number' => isset($shop['phone_number']) ? $shop['phone_number'] : '',
        'gst_no' => isset($shop['gst_no']) ? $shop['gst_no'] : '',
        'logo_url' => shop_to_array($shop)['logo_url'],
        'tag_credit_balance' => (int) $shop['tag_credit_balance'],
        'credits_expire_at' => as_iso($shop['credits_expire_at']),
        'unlimited_until' => as_iso($shop['unlimited_until']),
        'is_unlimited_active' => is_unlimited_active($shop),
        'is_active' => !isset($shop['is_active']) || (int) $shop['is_active'] === 1,
        'suspended_reason' => isset($shop['suspended_reason']) ? $shop['suspended_reason'] : null,
        'credits_expired' => $creditsExpired,
        'last_active_at' => isset($shop['last_active_at']) ? as_iso($shop['last_active_at']) : null,
        'last_tag_at' => $lastTag ? as_iso($lastTag['created_at']) : null,
        'created_at' => as_iso($shop['created_at']),
        'owner_email' => $owner ? $owner['email'] : null,
        'owner_name' => $owner ? $owner['name'] : null,
        'owner_id' => $owner ? (int) $owner['id'] : null,
    );
}

function as_utc_ts($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    $ts = strtotime($value . (strpos($value, 'Z') !== false || preg_match('/[+-]\d{2}:\d{2}$/', $value) ? '' : ' UTC'));
    return $ts === false ? null : $ts;
}

function touch_shop_activity($shopId)
{
    try {
        db_exec('UPDATE shops SET last_active_at = ? WHERE id = ?', array(now_utc(), (int) $shopId));
    } catch (Exception $e) {
        // Column may not exist on very old installs before migrate runs.
    }
}

function assert_shop_not_suspended($user, $shop)
{
    if ($user['role'] === 'admin') {
        return;
    }
    if (isset($shop['is_active']) && (int) $shop['is_active'] === 0) {
        $reason = !empty($shop['suspended_reason']) ? $shop['suspended_reason'] : 'Contact support.';
        json_error(403, 'This shop is suspended. ' . $reason);
    }
}

function platform_admin_shop_id()
{
    $admin = db_one("SELECT shop_id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if ($admin) {
        return (int) $admin['shop_id'];
    }
    $shop = db_one("SELECT id FROM shops WHERE short_name = 'ADMIN' OR name = 'Platform Admin' ORDER BY id ASC LIMIT 1");
    if ($shop) {
        return (int) $shop['id'];
    }
    db_exec("INSERT INTO shops (name, short_name, tag_credit_balance, is_active) VALUES ('Platform Admin', 'ADMIN', 0, 1)");
    return (int) db()->lastInsertId();
}

function get_shop($shopId, $forUpdate = false)
{
    $sql = 'SELECT * FROM shops WHERE id = ?';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $shop = db_one($sql, array($shopId));
    if (!$shop) {
        json_error(404, 'Shop not found');
    }
    return $shop;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf()
{
    $method = request_method();
    if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
        return;
    }
    $token = '';
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'x-csrf-token') {
                $token = (string) $value;
                break;
            }
        }
    }
    if ($token === '') {
        $data = request_json();
        if (!empty($data['csrf_token'])) {
            $token = (string) $data['csrf_token'];
        } elseif (!empty($_POST['csrf_token'])) {
            $token = (string) $_POST['csrf_token'];
        }
    }
    $sessionToken = csrf_token();
    if ($token === '' || !hash_equals($sessionToken, $token)) {
        json_error(403, 'Invalid security token. Refresh the page and try again.');
    }
}

function current_user()
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return db_one('SELECT * FROM users WHERE id = ? AND is_active = 1', array((int) $_SESSION['user_id']));
}

function support_view_from_session()
{
    if (empty($_SESSION['support_view']) || !is_array($_SESSION['support_view'])) {
        return null;
    }
    return $_SESSION['support_view'];
}

function clear_support_view()
{
    unset($_SESSION['support_view']);
}

function enforce_support_session()
{
    $view = support_view_from_session();
    if (!$view) {
        return null;
    }
    $expiresTs = !empty($view['expires_at']) ? strtotime($view['expires_at'] . ' UTC') : false;
    if ($expiresTs === false || $expiresTs < time()) {
        clear_support_view();
        $_SESSION = array();
        json_error(401, 'Support view session expired. Return to admin and start a new session.');
    }
    return $view;
}

function support_view_is_readonly()
{
    $view = support_view_from_session();
    return $view && isset($view['mode']) && $view['mode'] === 'readonly';
}

function is_shop_mutating_route($method, $route)
{
    $method = strtoupper((string) $method);
    if (!in_array($method, array('POST', 'PUT', 'DELETE', 'PATCH'), true)) {
        return false;
    }
    if (strpos($route, 'admin/') === 0) {
        return false;
    }
    $allowed = array(
        'auth/login' => true,
        'auth/register' => true,
        'auth/forgot-password' => true,
        'auth/reset-password' => true,
        'auth/logout' => true,
        'auth/support-start' => true,
        'auth/support-end' => true,
    );
    return empty($allowed[$route]);
}

function assert_support_allows_write($method = null, $route = null)
{
    if ($method === null) {
        $method = request_method();
    }
    if ($route === null) {
        $route = request_route();
    }
    if (!is_shop_mutating_route($method, $route)) {
        return;
    }
    if (support_view_is_readonly()) {
        json_error(403, 'Support view is read-only. Start a timed session from admin to make changes.');
    }
}

function support_view_to_array($view)
{
    if (!$view) {
        return null;
    }
    return array(
        'mode' => isset($view['mode']) ? $view['mode'] : 'readonly',
        'expires_at' => isset($view['expires_at']) ? as_iso($view['expires_at']) : null,
        'admin_name' => isset($view['admin_name']) ? $view['admin_name'] : null,
        'admin_email' => isset($view['admin_email']) ? $view['admin_email'] : null,
        'shop_id' => isset($view['shop_id']) ? (int) $view['shop_id'] : null,
        'readonly' => isset($view['mode']) && $view['mode'] === 'readonly',
    );
}

function shop_usage_stats($shopId)
{
    $shopId = (int) $shopId;
    $tagsCreated = (int) db_one('SELECT COUNT(*) AS c FROM jewellery_tags WHERE shop_id = ?', array($shopId))['c'];
    $totalPrintCopies = (int) db_one(
        'SELECT COALESCE(SUM(copies), 0) AS c FROM print_logs WHERE shop_id = ?',
        array($shopId)
    )['c'];
    $reprints = (int) db_one(
        'SELECT COALESCE(SUM(GREATEST(print_count - 1, 0)), 0) AS c FROM jewellery_tags WHERE shop_id = ?',
        array($shopId)
    )['c'];
    $creditsUsed = (int) db_one(
        "SELECT COALESCE(SUM(credits), 0) AS c FROM credit_ledger_entries WHERE shop_id = ? AND entry_type = 'debit'",
        array($shopId)
    )['c'];
    $lastPrint = db_one(
        'SELECT printed_at FROM print_logs WHERE shop_id = ? ORDER BY printed_at DESC, id DESC LIMIT 1',
        array($shopId)
    );
    $lastTag = db_one(
        'SELECT created_at FROM jewellery_tags WHERE shop_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
        array($shopId)
    );
    return array(
        'tags_created' => $tagsCreated,
        'total_prints' => $totalPrintCopies,
        'reprints' => $reprints,
        'credits_used' => $creditsUsed,
        'last_print_at' => $lastPrint ? as_iso($lastPrint['printed_at']) : null,
        'last_tag_at' => $lastTag ? as_iso($lastTag['created_at']) : null,
    );
}

function support_note_to_array($row)
{
    return array(
        'id' => (int) $row['id'],
        'shop_id' => (int) $row['shop_id'],
        'author_user_id' => (int) $row['author_user_id'],
        'author_name' => isset($row['author_name']) ? $row['author_name'] : null,
        'body' => $row['body'],
        'created_at' => as_iso($row['created_at']),
    );
}

function require_user()
{
    enforce_support_session();
    $user = current_user();
    if (!$user) {
        json_error(401, 'Please sign in');
    }
    assert_support_allows_write();
    if ($user['role'] !== 'admin' && !support_view_from_session()) {
        $shop = get_shop($user['shop_id']);
        assert_shop_not_suspended($user, $shop);
    }
    return $user;
}

function require_admin()
{
    $user = require_user();
    if ($user['role'] !== 'admin') {
        json_error(403, 'Admin access required');
    }
    if (support_view_from_session()) {
        json_error(403, 'Admin actions are not available during support view');
    }
    return $user;
}

function auth_payload($user, $shop)
{
    $platform = array(
        'announcement' => null,
        'features' => array(
            'razorpay' => true,
            'registration' => true,
            'reprints' => true,
        ),
        'free_registration_credits' => (int) app_config('free_registration_credits', 20),
        'free_registration_validity_days' => max(1, (int) app_config('free_registration_validity_days', 2)),
    );
    if (function_exists('platform_public_payload')) {
        try {
            $platform = platform_public_payload();
        } catch (Exception $e) {
            // keep defaults
        } catch (Throwable $e) {
            // keep defaults
        }
    }
    return array(
        'access_token' => 'session',
        'token_type' => 'session',
        'user' => user_to_array($user),
        'shop' => shop_to_array($shop),
        'support' => support_view_to_array(support_view_from_session()),
        'platform' => $platform,
        'csrf_token' => csrf_token(),
    );
}

function login_user($user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    clear_support_view();
    csrf_token();
    if ($user['role'] !== 'admin') {
        touch_shop_activity($user['shop_id']);
    }
}

function support_view_url($token)
{
    return app_base_url() . '/?support=' . rawurlencode($token);
}
