<?php

function dispatch_ops_api($method, $route)
{
    if ($route === 'admin/plans' && $method === 'POST') {
        handle_admin_create_plan(require_admin());
    }
    if ($route === 'admin/promos' && $method === 'GET') {
        handle_admin_list_promos(require_admin());
    }
    if ($route === 'admin/promos' && $method === 'POST') {
        handle_admin_create_promo(require_admin());
    }
    if ($route === 'admin/promos' && $method === 'PUT') {
        handle_admin_update_promo(require_admin());
    }
    if ($route === 'admin/settings' && $method === 'GET') {
        handle_admin_get_settings(require_admin());
    }
    if ($route === 'admin/settings' && $method === 'PUT') {
        handle_admin_save_settings(require_admin());
    }
    if ($route === 'admin/purchases/fail-alerts' && $method === 'GET') {
        handle_admin_fail_alerts(require_admin());
    }
    if ($route === 'admin/purchases/receipt' && $method === 'GET') {
        handle_admin_purchase_receipt(require_admin());
    }
    if ($route === 'admin/activity' && $method === 'GET') {
        handle_admin_activity_log(require_admin());
    }
    if ($route === 'admin/login-audit' && $method === 'GET') {
        handle_admin_login_audit(require_admin());
    }
    if ($route === 'admin/mail-outbox' && $method === 'GET') {
        handle_admin_mail_outbox(require_admin());
    }
    if ($route === 'admin/mail-outbox/read' && $method === 'GET') {
        handle_admin_mail_outbox_read(require_admin());
    }
    if ($route === 'admin/health' && $method === 'GET') {
        handle_admin_health(require_admin());
    }
    if ($route === 'admin/security/2fa' && $method === 'GET') {
        handle_admin_2fa_status(require_admin());
    }
    if ($route === 'admin/security/2fa' && $method === 'POST') {
        handle_admin_2fa_setup(require_admin());
    }
}

function handle_admin_create_plan($admin)
{
    $data = request_json();
    $code = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', require_string($data, 'code', 2, 40)));
    if ($code === '') {
        json_error(422, 'Plan code is required');
    }
    if (db_one('SELECT id FROM billing_plans WHERE code = ?', array($code))) {
        json_error(409, 'A plan with this code already exists');
    }
    $name = require_string($data, 'name', 2, 80);
    $description = require_string($data, 'description', 2, 240);
    $price = require_int($data, 'price_inr', 0, 10000000);
    $credits = require_int($data, 'tag_credits', 0, 10000000);
    $days = require_int($data, 'validity_days', 1, 3660);
    $unlimited = require_bool($data, 'is_unlimited') ? 1 : 0;
    $active = isset($data['is_active']) ? (require_bool($data, 'is_active') ? 1 : 0) : 1;
    $sort = isset($data['sort_order']) ? require_int($data, 'sort_order', 0, 100000) : 100;
    if (!$unlimited && $credits <= 0) {
        json_error(422, 'Credit plans need tag_credits > 0');
    }
    db_exec(
        'INSERT INTO billing_plans (code, name, description, price_inr, tag_credits, validity_days, is_unlimited, is_active, is_system, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
        array($code, $name, $description, $price, $credits, $days, $unlimited, $active, $sort)
    );
    $id = (int) db()->lastInsertId();
    log_admin_activity($admin, 'plan.create', 'billing_plan', $id, $code . ' / ' . $name);
    json_ok(plan_to_array(db_one('SELECT * FROM billing_plans WHERE id = ?', array($id))), 201);
}

function handle_admin_list_promos($admin)
{
    ensure_ops_schema();
    $rows = db_all('SELECT * FROM promo_codes ORDER BY created_at DESC, id DESC LIMIT 200');
    json_ok(array_map('promo_to_array', $rows));
}

function handle_admin_create_promo($admin)
{
    ensure_ops_schema();
    $data = request_json();
    $code = strtoupper(preg_replace('/\s+/', '', require_string($data, 'code', 2, 40)));
    if (db_one('SELECT id FROM promo_codes WHERE code = ?', array($code))) {
        json_error(409, 'Promo code already exists');
    }
    $description = optional_string($data, 'description', 240);
    $credits = require_int($data, 'tag_credits', 0, 1000000);
    $days = require_int($data, 'validity_days', 1, 3660);
    $unlimited = !empty($data['is_unlimited']) ? 1 : 0;
    $max = isset($data['max_redemptions']) ? require_int($data, 'max_redemptions', 0, 1000000) : 0;
    if (!$unlimited && $credits <= 0) {
        json_error(422, 'Provide tag_credits or set is_unlimited');
    }
    db_exec(
        'INSERT INTO promo_codes (code, description, tag_credits, validity_days, is_unlimited, max_redemptions, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)',
        array($code, $description ?: '', $credits, $days, $unlimited, $max)
    );
    $id = (int) db()->lastInsertId();
    log_admin_activity($admin, 'promo.create', 'promo_code', $id, $code);
    json_ok(promo_to_array(db_one('SELECT * FROM promo_codes WHERE id = ?', array($id))), 201);
}

function handle_admin_update_promo($admin)
{
    ensure_ops_schema();
    $data = request_json();
    $id = isset($data['id']) ? (int) $data['id'] : 0;
    $promo = db_one('SELECT * FROM promo_codes WHERE id = ?', array($id));
    if (!$promo) {
        json_error(404, 'Promo not found');
    }
    $description = optional_string($data, 'description', 240);
    $credits = require_int($data, 'tag_credits', 0, 1000000);
    $days = require_int($data, 'validity_days', 1, 3660);
    $unlimited = !empty($data['is_unlimited']) ? 1 : 0;
    $max = isset($data['max_redemptions']) ? require_int($data, 'max_redemptions', 0, 1000000) : (int) $promo['max_redemptions'];
    $active = !empty($data['is_active']) ? 1 : 0;
    db_exec(
        'UPDATE promo_codes SET description = ?, tag_credits = ?, validity_days = ?, is_unlimited = ?, max_redemptions = ?, is_active = ? WHERE id = ?',
        array($description ?: '', $credits, $days, $unlimited, $max, $active, $id)
    );
    log_admin_activity($admin, 'promo.update', 'promo_code', $id, $promo['code']);
    json_ok(promo_to_array(db_one('SELECT * FROM promo_codes WHERE id = ?', array($id))));
}

function handle_admin_get_settings($admin)
{
    json_ok(platform_settings_all());
}

function handle_admin_save_settings($admin)
{
    $data = request_json();
    if (!is_array($data)) {
        json_error(422, 'Invalid settings payload');
    }
    set_platform_settings($data, (int) $admin['id']);
    log_admin_activity($admin, 'settings.update', 'platform_settings', null, 'Updated platform settings');
    json_ok(platform_settings_all());
}

function handle_admin_fail_alerts($admin)
{
    $rows = db_all(
        "SELECT p.*, bp.name AS plan_name, bp.code AS plan_code, s.name AS shop_name, u.email AS owner_email
         FROM plan_purchases p
         LEFT JOIN billing_plans bp ON bp.id = p.plan_id
         LEFT JOIN shops s ON s.id = p.shop_id
         LEFT JOIN users u ON u.shop_id = s.id AND u.role = 'owner'
         WHERE (
            p.status = 'failed'
            OR (
              p.status = 'pending'
              AND p.razorpay_order_id IS NOT NULL
              AND p.razorpay_order_id NOT LIKE 'local_order_%'
              AND p.created_at < (UTC_TIMESTAMP() - INTERVAL 1 HOUR)
            )
         )
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 200"
    );
    $out = array();
    foreach ($rows as $row) {
        $item = purchase_to_array($row);
        $item['plan_name'] = isset($row['plan_name']) ? $row['plan_name'] : null;
        $item['plan_code'] = isset($row['plan_code']) ? $row['plan_code'] : null;
        $item['shop_name'] = isset($row['shop_name']) ? $row['shop_name'] : null;
        $item['owner_email'] = isset($row['owner_email']) ? $row['owner_email'] : null;
        $item['alert_reason'] = $row['status'] === 'failed' ? 'failed' : 'stale_pending';
        $out[] = $item;
    }
    json_ok($out);
}

function handle_admin_purchase_receipt($admin)
{
    $purchaseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $row = db_one(
        "SELECT p.*, bp.name AS plan_name, bp.code AS plan_code, s.name AS shop_name,
                s.address AS shop_address, s.phone_number AS shop_phone, s.gst_no AS shop_gst,
                u.name AS owner_name, u.email AS owner_email
         FROM plan_purchases p
         LEFT JOIN billing_plans bp ON bp.id = p.plan_id
         LEFT JOIN shops s ON s.id = p.shop_id
         LEFT JOIN users u ON u.shop_id = s.id AND u.role = 'owner'
         WHERE p.id = ?",
        array($purchaseId)
    );
    if (!$row) {
        json_error(404, 'Purchase not found');
    }
    if ($row['status'] !== 'paid' && empty($row['paid_at'])) {
        json_error(422, 'Receipt is only available for paid purchases');
    }

    $settings = platform_settings_all();
    $amount = (int) $row['amount_inr'];
    $taxable = round($amount / 1.18, 2);
    $gst = round($amount - $taxable, 2);
    $cgst = round($gst / 2, 2);
    $sgst = round($gst - $cgst, 2);
    $invoiceNo = 'TF-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT);
    $paidAt = $row['paid_at'] ? $row['paid_at'] : $row['created_at'];

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $esc = function ($v) {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    };
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice ' . $esc($invoiceNo) . '</title>';
    echo '<style>
      body{font-family:Segoe UI,Arial,sans-serif;color:#111;margin:32px;max-width:800px}
      h1{margin:0 0 4px;font-size:22px} .muted{color:#555;font-size:13px}
      table{width:100%;border-collapse:collapse;margin-top:18px}
      th,td{border:1px solid #ddd;padding:8px 10px;text-align:left;font-size:14px}
      th{background:#f5f5f5} .totals td{border:0;text-align:right}
      .grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:18px}
      @media print{button{display:none} body{margin:12px}}
    </style></head><body>';
    echo '<button onclick="window.print()">Print / Save PDF</button>';
    echo '<h1>Tax Invoice / Receipt</h1>';
    echo '<p class="muted">Invoice ' . $esc($invoiceNo) . ' · Paid ' . $esc($paidAt) . ' UTC</p>';
    echo '<div class="grid"><div><strong>From (Platform)</strong><br>'
        . $esc($settings['invoice_legal_name']) . '<br>'
        . $esc($settings['invoice_address']) . '<br>'
        . 'GSTIN: ' . $esc($settings['invoice_gstin'] ?: '—') . '<br>'
        . $esc($settings['invoice_email'])
        . '</div><div><strong>Bill to</strong><br>'
        . $esc($row['shop_name']) . '<br>'
        . $esc($row['shop_address']) . '<br>'
        . 'Phone: ' . $esc($row['shop_phone'] ?: '—') . '<br>'
        . 'GSTIN: ' . $esc($row['shop_gst'] ?: '—') . '<br>'
        . $esc($row['owner_name']) . ' · ' . $esc($row['owner_email'])
        . '</div></div>';
    echo '<table><thead><tr><th>Description</th><th>Method</th><th>Amount (INR)</th></tr></thead><tbody>';
    echo '<tr><td>' . $esc($row['plan_name'] ?: 'Plan') . ' (' . $esc($row['plan_code'] ?: '') . ')<br>'
        . '<span class="muted">' . ((int) $row['is_unlimited'] ? 'Unlimited' : ((int) $row['tag_credits'] . ' credits'))
        . ' · ' . (int) $row['validity_days'] . ' days</span></td>'
        . '<td>' . $esc(isset($row['payment_method']) ? $row['payment_method'] : 'razorpay') . '</td>'
        . '<td>₹' . number_format($amount, 2) . '</td></tr>';
    echo '</tbody></table>';
    echo '<table class="totals"><tr><td>Taxable value (approx)</td><td>₹' . number_format($taxable, 2) . '</td></tr>';
    echo '<tr><td>CGST @9%</td><td>₹' . number_format($cgst, 2) . '</td></tr>';
    echo '<tr><td>SGST @9%</td><td>₹' . number_format($sgst, 2) . '</td></tr>';
    echo '<tr><td><strong>Total paid</strong></td><td><strong>₹' . number_format($amount, 2) . '</strong></td></tr></table>';
    echo '<p class="muted">Payment ref: ' . $esc($row['razorpay_payment_id'] ?: $row['receipt_note'] ?: '—') . '</p>';
    echo '<p class="muted">This is a computer-generated receipt from TagForge. GST split is indicative for inclusive pricing.</p>';
    echo '</body></html>';
    exit;
}

function handle_admin_activity_log($admin)
{
    ensure_ops_schema();
    $rows = db_all(
        'SELECT a.*, u.name AS admin_name, u.email AS admin_email
         FROM admin_activity_log a
         LEFT JOIN users u ON u.id = a.admin_user_id
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT 200'
    );
    json_ok(array_map('activity_to_array', $rows));
}

function handle_admin_login_audit($admin)
{
    ensure_ops_schema();
    $onlyFailed = isset($_GET['failed']) && $_GET['failed'] !== '0';
    $sql = 'SELECT * FROM login_audit';
    if ($onlyFailed) {
        $sql .= ' WHERE success = 0 AND is_admin = 1';
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';
    json_ok(array_map('login_audit_to_array', db_all($sql)));
}

function handle_admin_mail_outbox($admin)
{
    $dir = upload_root() . DIRECTORY_SEPARATOR . 'mail_outbox';
    if (!is_dir($dir)) {
        json_ok(array());
    }
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.txt');
    if (!$files) {
        json_ok(array());
    }
    rsort($files);
    $out = array();
    foreach (array_slice($files, 0, 100) as $path) {
        $name = basename($path);
        $out[] = array(
            'file' => $name,
            'size' => filesize($path),
            'modified_at' => gmdate('c', filemtime($path)),
        );
    }
    json_ok($out);
}

function handle_admin_mail_outbox_read($admin)
{
    $file = isset($_GET['file']) ? basename((string) $_GET['file']) : '';
    if ($file === '' || !preg_match('/^[A-Za-z0-9._@-]+\.txt$/', $file)) {
        json_error(422, 'Invalid outbox file');
    }
    $path = upload_root() . DIRECTORY_SEPARATOR . 'mail_outbox' . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        json_error(404, 'Outbox file not found');
    }
    json_ok(array(
        'file' => $file,
        'content' => file_get_contents($path),
    ));
}

function handle_admin_health($admin)
{
    $dbOk = false;
    $dbDetail = '';
    try {
        db()->query('SELECT 1');
        $dbOk = true;
        $dbDetail = 'Connected';
    } catch (Exception $e) {
        $dbDetail = $e->getMessage();
    }

    $uploadRoot = upload_root();
    $freeBytes = @disk_free_space($uploadRoot);
    $totalBytes = @disk_total_space($uploadRoot);
    $writable = is_dir($uploadRoot) && is_writable($uploadRoot);

    $mailFrom = trim((string) app_config('mail_from', ''));
    $mailDebug = (bool) app_config('mail_debug', false);
    $outboxDir = $uploadRoot . DIRECTORY_SEPARATOR . 'mail_outbox';
    $outboxCount = is_dir($outboxDir) ? count(glob($outboxDir . DIRECTORY_SEPARATOR . '*.txt') ?: array()) : 0;

    json_ok(array(
        'app' => app_config('app_name', 'TagForge'),
        'time_utc' => gmdate('c'),
        'database' => array('ok' => $dbOk, 'detail' => $dbDetail),
        'uploads' => array(
            'ok' => $writable,
            'path' => $uploadRoot,
            'writable' => $writable,
            'free_bytes' => $freeBytes !== false ? (int) $freeBytes : null,
            'total_bytes' => $totalBytes !== false ? (int) $totalBytes : null,
        ),
        'mail' => array(
            'from' => $mailFrom,
            'debug' => $mailDebug,
            'configured' => $mailFrom !== '',
            'outbox_files' => $outboxCount,
        ),
        'razorpay' => array(
            'configured' => razorpay_configured(),
            'feature_enabled' => feature_enabled('razorpay'),
        ),
        'features' => platform_public_payload()['features'],
    ));
}

function handle_admin_2fa_status($admin)
{
    $user = db_one('SELECT * FROM users WHERE id = ?', array((int) $admin['id']));
    json_ok(array(
        'totp_enabled' => !empty($user['totp_enabled']),
        'email_otp_enabled' => !empty($user['email_otp_enabled']),
        'has_totp_secret' => !empty($user['totp_secret']),
    ));
}

function handle_admin_2fa_setup($admin)
{
    ensure_ops_schema();
    $data = request_json();
    $action = isset($data['action']) ? (string) $data['action'] : '';
    $user = db_one('SELECT * FROM users WHERE id = ?', array((int) $admin['id']));

    if ($action === 'enable_email_otp') {
        db_exec('UPDATE users SET email_otp_enabled = 1 WHERE id = ?', array($user['id']));
        log_admin_activity($admin, '2fa.email_enable', 'user', $user['id'], null);
        json_ok(array('email_otp_enabled' => true));
    }
    if ($action === 'disable_email_otp') {
        db_exec('UPDATE users SET email_otp_enabled = 0 WHERE id = ?', array($user['id']));
        log_admin_activity($admin, '2fa.email_disable', 'user', $user['id'], null);
        json_ok(array('email_otp_enabled' => false));
    }
    if ($action === 'begin_totp') {
        $secret = generate_totp_secret();
        $_SESSION['pending_totp_secret'] = $secret;
        $issuer = rawurlencode(app_config('app_name', 'TagForge'));
        $label = rawurlencode($user['email']);
        $otpauth = 'otpauth://totp/' . $issuer . ':' . $label . '?secret=' . $secret . '&issuer=' . $issuer . '&digits=6&period=30';
        json_ok(array('secret' => $secret, 'otpauth_url' => $otpauth));
    }
    if ($action === 'confirm_totp') {
        $code = isset($data['code']) ? (string) $data['code'] : '';
        $secret = isset($_SESSION['pending_totp_secret']) ? $_SESSION['pending_totp_secret'] : '';
        if ($secret === '' || !verify_totp_code($secret, $code)) {
            json_error(422, 'Invalid authenticator code');
        }
        db_exec('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?', array($secret, $user['id']));
        unset($_SESSION['pending_totp_secret']);
        log_admin_activity($admin, '2fa.totp_enable', 'user', $user['id'], null);
        json_ok(array('totp_enabled' => true));
    }
    if ($action === 'disable_totp') {
        $code = isset($data['code']) ? (string) $data['code'] : '';
        if (!empty($user['totp_enabled']) && !verify_totp_code($user['totp_secret'], $code)) {
            json_error(422, 'Invalid authenticator code');
        }
        db_exec('UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE id = ?', array($user['id']));
        log_admin_activity($admin, '2fa.totp_disable', 'user', $user['id'], null);
        json_ok(array('totp_enabled' => false));
    }
    json_error(422, 'Unknown 2FA action');
}
