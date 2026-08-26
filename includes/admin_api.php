<?php

function dispatch_admin_api($method, $route)
{
    if ($route === 'admin/dashboard' && $method === 'GET') {
        handle_admin_dashboard(require_admin());
    }
    if ($route === 'admin/tenants' && $method === 'GET') {
        handle_admin_list_tenants(require_admin());
    }
    if ($route === 'admin/tenants/detail' && $method === 'GET') {
        handle_admin_tenant_detail(require_admin());
    }
    if ($route === 'admin/tenants/status' && $method === 'POST') {
        handle_admin_tenant_status(require_admin());
    }
    if ($route === 'admin/tenants/offline-payment' && $method === 'POST') {
        handle_admin_offline_payment(require_admin());
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
    if ($route === 'admin/tenants/impersonate' && $method === 'POST') {
        handle_admin_impersonate(require_admin());
    }
    if ($route === 'admin/tenants/profile' && $method === 'PUT') {
        handle_admin_edit_tenant_profile(require_admin());
    }
    if ($route === 'admin/tenants/notes' && $method === 'GET') {
        handle_admin_list_notes(require_admin());
    }
    if ($route === 'admin/tenants/notes' && $method === 'POST') {
        handle_admin_add_note(require_admin());
    }
    if ($route === 'admin/tenants/notes' && $method === 'DELETE') {
        handle_admin_delete_note(require_admin());
    }
    if ($route === 'admin/tenants/send-reset' && $method === 'POST') {
        handle_admin_send_owner_reset(require_admin());
    }
    if ($route === 'admin/tenants/export' && $method === 'GET') {
        handle_admin_export_tenants(require_admin());
    }
    if ($route === 'admin/purchases' && $method === 'GET') {
        handle_admin_list_purchases(require_admin());
    }
    if ($route === 'admin/purchases/refund' && $method === 'POST') {
        handle_admin_refund_purchase(require_admin());
    }
    if ($route === 'admin/users' && $method === 'GET') {
        handle_admin_list_admins(require_admin());
    }
    if ($route === 'admin/users' && $method === 'POST') {
        handle_admin_create_admin(require_admin());
    }
    if ($route === 'admin/users/reset-password' && $method === 'POST') {
        handle_admin_reset_password(require_admin());
    }
    if ($route === 'admin/plans' && $method === 'GET') {
        require_admin();
        $rows = db_all('SELECT * FROM billing_plans ORDER BY sort_order ASC, price_inr ASC');
        json_ok(array_map('plan_to_array', $rows));
    }
    if ($route === 'admin/plans' && $method === 'PUT') {
        handle_admin_update_plan(require_admin());
    }

    // Extended billing / platform / security ops
    dispatch_ops_api($method, $route);
}

function handle_admin_dashboard($admin)
{
    $totalShops = (int) db_one("SELECT COUNT(*) AS c FROM shops WHERE COALESCE(short_name, '') <> 'ADMIN'")['c'];
    $activeUnlimited = (int) db_one(
        "SELECT COUNT(*) AS c FROM shops
         WHERE COALESCE(short_name, '') <> 'ADMIN'
           AND unlimited_until IS NOT NULL
           AND unlimited_until > UTC_TIMESTAMP()"
    )['c'];
    $tagsToday = (int) db_one(
        "SELECT COUNT(*) AS c FROM jewellery_tags WHERE DATE(created_at) = UTC_DATE()"
    )['c'];
    $tagsMonth = (int) db_one(
        "SELECT COUNT(*) AS c FROM jewellery_tags
         WHERE created_at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')"
    )['c'];
    $revenueMonth = (int) db_one(
        "SELECT COALESCE(SUM(amount_inr), 0) AS c FROM plan_purchases
         WHERE status = 'paid'
           AND (refunded_at IS NULL)
           AND paid_at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')"
    )['c'];
    $pendingPurchases = (int) db_one("SELECT COUNT(*) AS c FROM plan_purchases WHERE status = 'pending'")['c'];
    $failedPurchases = (int) db_one(
        "SELECT COUNT(*) AS c FROM plan_purchases
         WHERE status = 'failed'
            OR (
              status = 'pending'
              AND razorpay_order_id IS NOT NULL
              AND razorpay_order_id NOT LIKE 'local_order_%'
              AND created_at < (UTC_TIMESTAMP() - INTERVAL 1 HOUR)
            )"
    )['c'];
    $suspendedShops = (int) db_one(
        "SELECT COUNT(*) AS c FROM shops WHERE COALESCE(is_active, 1) = 0 AND COALESCE(short_name, '') <> 'ADMIN'"
    )['c'];

    $lowCredit = db_all(
        "SELECT s.* FROM shops s
         WHERE COALESCE(s.short_name, '') <> 'ADMIN'
           AND COALESCE(s.is_active, 1) = 1
           AND (s.unlimited_until IS NULL OR s.unlimited_until <= UTC_TIMESTAMP())
           AND s.tag_credit_balance <= 5
         ORDER BY s.tag_credit_balance ASC, s.name ASC
         LIMIT 20"
    );

    json_ok(array(
        'total_shops' => $totalShops,
        'active_unlimited' => $activeUnlimited,
        'tags_today' => $tagsToday,
        'tags_month' => $tagsMonth,
        'revenue_month_inr' => $revenueMonth,
        'pending_purchases' => $pendingPurchases,
        'failed_payment_alerts' => $failedPurchases,
        'suspended_shops' => $suspendedShops,
        'low_credit_shops' => array_map('admin_tenant_response', $lowCredit),
    ));
}

function handle_admin_list_tenants($admin)
{
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $status = isset($_GET['status']) ? trim((string) $_GET['status']) : 'all';
    $credits = isset($_GET['credits']) ? trim((string) $_GET['credits']) : 'all';
    $sql = "SELECT s.* FROM shops s
            LEFT JOIN users u ON u.shop_id = s.id AND u.role = 'owner'
            WHERE COALESCE(s.short_name, '') <> 'ADMIN'";
    $params = array();

    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= ' AND (s.name LIKE ? OR s.short_name LIKE ? OR u.email LIKE ? OR u.name LIKE ? OR s.phone_number LIKE ? OR s.gst_no LIKE ?)';
        $params = array_merge($params, array($like, $like, $like, $like, $like, $like));
    }

    if ($status === 'active') {
        $sql .= ' AND COALESCE(s.is_active, 1) = 1';
    } elseif ($status === 'suspended') {
        $sql .= ' AND COALESCE(s.is_active, 1) = 0';
    } elseif ($status === 'unlimited') {
        $sql .= ' AND s.unlimited_until IS NOT NULL AND s.unlimited_until > UTC_TIMESTAMP()';
    } elseif ($status === 'expired') {
        $sql .= ' AND (s.unlimited_until IS NULL OR s.unlimited_until <= UTC_TIMESTAMP())
                  AND s.credits_expire_at IS NOT NULL AND s.credits_expire_at <= UTC_TIMESTAMP()';
    }

    if ($credits === 'low') {
        $sql .= ' AND s.tag_credit_balance <= 5 AND (s.unlimited_until IS NULL OR s.unlimited_until <= UTC_TIMESTAMP())';
    } elseif ($credits === 'zero') {
        $sql .= ' AND s.tag_credit_balance = 0 AND (s.unlimited_until IS NULL OR s.unlimited_until <= UTC_TIMESTAMP())';
    }

    $sql .= ' ORDER BY COALESCE(s.last_active_at, s.created_at) DESC, s.id DESC LIMIT 200';
    $shops = db_all($sql, $params);
    json_ok(array_map('admin_tenant_response', $shops));
}

function handle_admin_tenant_detail($admin)
{
    $shopId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $shop = get_shop($shopId);
    $tenant = admin_tenant_response($shop);

    $tags = db_all(
        'SELECT * FROM jewellery_tags WHERE shop_id = ? ORDER BY created_at DESC, id DESC LIMIT 20',
        array($shopId)
    );
    $prints = db_all(
        'SELECT pl.*, jt.tag_number, u.name AS printed_by_name
         FROM print_logs pl
         LEFT JOIN jewellery_tags jt ON jt.id = pl.jewellery_tag_id
         LEFT JOIN users u ON u.id = pl.printed_by
         WHERE pl.shop_id = ?
         ORDER BY pl.printed_at DESC, pl.id DESC
         LIMIT 20',
        array($shopId)
    );
    $purchases = db_all(
        'SELECT p.*, bp.name AS plan_name, bp.code AS plan_code
         FROM plan_purchases p
         LEFT JOIN billing_plans bp ON bp.id = p.plan_id
         WHERE p.shop_id = ?
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 50',
        array($shopId)
    );
    $ledger = db_all(
        'SELECT * FROM credit_ledger_entries WHERE shop_id = ? ORDER BY created_at DESC, id DESC LIMIT 50',
        array($shopId)
    );

    $tagCount = (int) db_one('SELECT COUNT(*) AS c FROM jewellery_tags WHERE shop_id = ?', array($shopId))['c'];
    $printCount = (int) db_one('SELECT COALESCE(SUM(copies),0) AS c FROM print_logs WHERE shop_id = ?', array($shopId))['c'];
    $usage = shop_usage_stats($shopId);

    $notes = db_all(
        'SELECT n.*, u.name AS author_name
         FROM shop_support_notes n
         LEFT JOIN users u ON u.id = n.author_user_id
         WHERE n.shop_id = ?
         ORDER BY n.created_at DESC, n.id DESC
         LIMIT 50',
        array($shopId)
    );

    $purchaseOut = array();
    foreach ($purchases as $row) {
        $item = purchase_to_array($row);
        $item['plan_name'] = isset($row['plan_name']) ? $row['plan_name'] : null;
        $item['plan_code'] = isset($row['plan_code']) ? $row['plan_code'] : null;
        $item['shop_name'] = $shop['name'];
        $purchaseOut[] = $item;
    }

    $printOut = array();
    foreach ($prints as $row) {
        $printOut[] = array(
            'id' => (int) $row['id'],
            'tag_number' => $row['tag_number'],
            'copies' => (int) $row['copies'],
            'printed_by_name' => $row['printed_by_name'],
            'printed_at' => as_iso($row['printed_at']),
        );
    }

    json_ok(array(
        'tenant' => $tenant,
        'stats' => array(
            'total_tags' => $tagCount,
            'total_prints' => $printCount,
        ),
        'usage' => $usage,
        'notes' => array_map('support_note_to_array', $notes),
        'recent_tags' => array_map('tag_to_array', $tags),
        'recent_prints' => $printOut,
        'purchases' => $purchaseOut,
        'ledger' => array_map('ledger_to_array', $ledger),
    ));
}

function handle_admin_tenant_status($admin)
{
    $data = request_json();
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : 0;
    $active = !empty($data['is_active']);
    $reason = optional_string($data, 'suspended_reason', 240);
    $shop = get_shop($shopId);
    if ($shop['short_name'] === 'ADMIN') {
        json_error(422, 'Cannot suspend the platform admin shop');
    }
    db_exec(
        'UPDATE shops SET is_active = ?, suspended_reason = ? WHERE id = ?',
        array($active ? 1 : 0, $active ? null : ($reason ?: 'Suspended by admin'), $shopId)
    );
    log_admin_activity($admin, $active ? 'tenant.activate' : 'tenant.suspend', 'shop', $shopId, $reason);
    json_ok(admin_tenant_response(get_shop($shopId)));
}

function handle_admin_offline_payment($admin)
{
    $data = request_json();
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : 0;
    $method = require_string($data, 'payment_method', 3, 40);
    $allowed = array('cash', 'upi', 'bank_transfer', 'manual');
    if (!in_array($method, $allowed, true)) {
        json_error(422, 'payment_method must be cash, upi, bank_transfer, or manual');
    }
    $amount = require_int($data, 'amount_inr', 0, 10000000);
    $receipt = optional_string($data, 'receipt_note', 240);
    $note = require_string($data, 'note', 3, 240);
    $credits = isset($data['tag_credits']) ? require_int($data, 'tag_credits', 0, 1000000) : 0;
    $unlimitedDays = isset($data['unlimited_days']) ? require_int($data, 'unlimited_days', 0, 3660) : 0;
    $planId = isset($data['plan_id']) ? (int) $data['plan_id'] : 0;

    if ($credits <= 0 && $unlimitedDays <= 0) {
        json_error(422, 'Provide tag_credits and/or unlimited_days');
    }

    $plan = null;
    if ($planId > 0) {
        $plan = db_one('SELECT * FROM billing_plans WHERE id = ?', array($planId));
    }
    if (!$plan) {
        $plan = db_one('SELECT * FROM billing_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1');
    }
    if (!$plan) {
        json_error(422, 'No billing plan available to attach this payment');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $shop = get_shop($shopId, true);
        $validityDays = $unlimitedDays > 0 ? $unlimitedDays : (int) $plan['validity_days'];
        db_exec(
            'INSERT INTO plan_purchases
                (shop_id, plan_id, amount_inr, tag_credits, validity_days, is_unlimited, status, payment_method,
                 razorpay_order_id, razorpay_payment_id, notes, receipt_note, paid_at, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $shopId,
                $plan['id'],
                $amount,
                $credits,
                $validityDays,
                $unlimitedDays > 0 ? 1 : 0,
                'paid',
                $method,
                'offline_' . $method . '_' . time(),
                $receipt ?: ('offline_' . $method),
                'months=' . max(1, (int) ceil($validityDays / 30)) . '; ' . $note,
                $receipt,
                now_utc(),
                (int) $admin['id'],
            )
        );
        $purchaseId = (int) $pdo->lastInsertId();
        $purchase = db_one('SELECT * FROM plan_purchases WHERE id = ?', array($purchaseId));
        if ($unlimitedDays > 0) {
            $purchase['is_unlimited'] = 1;
            $purchase['validity_days'] = $unlimitedDays;
            $purchase['tag_credits'] = 0;
            grant_purchase_to_shop($shop, $purchase);
        } elseif ($credits > 0) {
            $purchase['is_unlimited'] = 0;
            $purchase['tag_credits'] = $credits;
            $purchase['validity_days'] = $validityDays > 0 ? $validityDays : 90;
            grant_purchase_to_shop($shop, $purchase);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_ok(array(
        'tenant' => admin_tenant_response(get_shop($shopId)),
        'purchase' => purchase_to_array(db_one('SELECT * FROM plan_purchases WHERE id = ?', array($purchaseId))),
    ), 201);
}

function handle_admin_list_purchases($admin)
{
    $status = isset($_GET['status']) ? trim((string) $_GET['status']) : 'all';
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $sql = 'SELECT p.*, bp.name AS plan_name, bp.code AS plan_code, s.name AS shop_name
            FROM plan_purchases p
            LEFT JOIN billing_plans bp ON bp.id = p.plan_id
            LEFT JOIN shops s ON s.id = p.shop_id
            WHERE 1=1';
    $params = array();
    if ($status === 'paid') {
        $sql .= " AND p.status = 'paid' AND p.refunded_at IS NULL";
    } elseif ($status === 'pending') {
        $sql .= " AND p.status = 'pending'";
    } elseif ($status === 'failed') {
        $sql .= " AND p.status = 'failed'";
    } elseif ($status === 'refunded') {
        $sql .= " AND (p.status = 'refunded' OR p.refunded_at IS NOT NULL)";
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= ' AND (s.name LIKE ? OR p.razorpay_payment_id LIKE ? OR p.razorpay_order_id LIKE ? OR p.receipt_note LIKE ? OR p.payment_method LIKE ?)';
        $params = array_merge($params, array($like, $like, $like, $like, $like));
    }
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT 200';
    $rows = db_all($sql, $params);
    $out = array();
    foreach ($rows as $row) {
        $item = purchase_to_array($row);
        $item['plan_name'] = isset($row['plan_name']) ? $row['plan_name'] : null;
        $item['plan_code'] = isset($row['plan_code']) ? $row['plan_code'] : null;
        $item['shop_name'] = isset($row['shop_name']) ? $row['shop_name'] : null;
        $out[] = $item;
    }
    json_ok($out);
}

function handle_admin_refund_purchase($admin)
{
    $data = request_json();
    $purchaseId = isset($data['id']) ? (int) $data['id'] : 0;
    $note = require_string($data, 'note', 3, 240);
    $revoke = !isset($data['revoke_benefits']) || !empty($data['revoke_benefits']);

    $purchase = db_one('SELECT * FROM plan_purchases WHERE id = ?', array($purchaseId));
    if (!$purchase) {
        json_error(404, 'Purchase not found');
    }
    if ($purchase['status'] !== 'paid' || !empty($purchase['refunded_at'])) {
        if (!empty($purchase['refunded_at']) || $purchase['status'] === 'refunded') {
            json_error(422, 'Purchase is already refunded');
        }
        json_error(422, 'Only paid purchases can be refunded');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $purchase = db_one('SELECT * FROM plan_purchases WHERE id = ? FOR UPDATE', array($purchaseId));
        if (!$purchase || $purchase['status'] !== 'paid' || !empty($purchase['refunded_at'])) {
            throw new RuntimeException('Purchase is no longer refundable');
        }

        $shop = get_shop($purchase['shop_id'], true);
        db_exec(
            "UPDATE plan_purchases SET status = 'refunded', refunded_at = ?, notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?",
            array(now_utc(), ' | refund: ' . $note, $purchaseId)
        );

        if ($revoke) {
            if ((int) $purchase['is_unlimited'] === 1) {
                db_exec('UPDATE shops SET unlimited_until = NULL WHERE id = ?', array($shop['id']));
                $shop['unlimited_until'] = null;
            }
            if ((int) $purchase['tag_credits'] > 0) {
                $old = (int) $shop['tag_credit_balance'];
                $shop['tag_credit_balance'] = max(0, $old - (int) $purchase['tag_credits']);
                db_exec('UPDATE shops SET tag_credit_balance = ? WHERE id = ?', array($shop['tag_credit_balance'], $shop['id']));
                add_ledger_entry(
                    $shop['id'],
                    'debit',
                    (int) $purchase['tag_credits'],
                    $shop['tag_credit_balance'],
                    'Refund: ' . $note,
                    null,
                    $purchaseId
                );
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    $row = db_one(
        'SELECT p.*, bp.name AS plan_name, bp.code AS plan_code, s.name AS shop_name
         FROM plan_purchases p
         LEFT JOIN billing_plans bp ON bp.id = p.plan_id
         LEFT JOIN shops s ON s.id = p.shop_id
         WHERE p.id = ?',
        array($purchaseId)
    );
    $item = purchase_to_array($row);
    $item['plan_name'] = $row['plan_name'];
    $item['plan_code'] = $row['plan_code'];
    $item['shop_name'] = $row['shop_name'];
    json_ok($item);
}

function handle_admin_list_admins($admin)
{
    $rows = db_all("SELECT id, shop_id, name, email, role, is_active, created_at FROM users WHERE role = 'admin' ORDER BY id ASC");
    $out = array();
    foreach ($rows as $row) {
        $out[] = array(
            'id' => (int) $row['id'],
            'shop_id' => (int) $row['shop_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'is_active' => (bool) $row['is_active'],
            'created_at' => as_iso($row['created_at']),
        );
    }
    json_ok($out);
}

function handle_admin_create_admin($admin)
{
    $data = request_json();
    $name = require_string($data, 'name', 2, 120);
    $email = require_email($data);
    $password = require_string($data, 'password', 8, 128, 'password');
    if (db_one('SELECT id FROM users WHERE email = ?', array($email))) {
        json_error(409, 'Email is already registered');
    }
    $shopId = platform_admin_shop_id();
    db_exec(
        'INSERT INTO users (shop_id, name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, 1)',
        array($shopId, $name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin')
    );
    $user = db_one('SELECT id, shop_id, name, email, role, is_active, created_at FROM users WHERE id = ?', array((int) db()->lastInsertId()));
    json_ok(array(
        'id' => (int) $user['id'],
        'shop_id' => (int) $user['shop_id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'is_active' => (bool) $user['is_active'],
        'created_at' => as_iso($user['created_at']),
    ), 201);
}

function handle_admin_reset_password($admin)
{
    ensure_password_reset_schema();
    $data = request_json();
    $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
    $newPassword = isset($data['password']) ? trim((string) $data['password']) : '';
    $sendEmail = !empty($data['send_email']);

    $user = db_one('SELECT * FROM users WHERE id = ?', array($userId));
    if (!$user) {
        json_error(404, 'User not found');
    }
    if ($user['role'] !== 'admin' && empty($data['allow_shop_user'])) {
        // Allow resetting shop owner passwords from tenant detail as well.
        if ($user['role'] !== 'owner') {
            json_error(422, 'Only admin or owner passwords can be reset here');
        }
    }

    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            json_error(422, 'Password must be at least 8 characters');
        }
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', array(password_hash($newPassword, PASSWORD_DEFAULT), $userId));
        json_ok(array('ok' => true, 'detail' => 'Password updated', 'mode' => 'direct'));
    }

    if (!$sendEmail) {
        json_error(422, 'Provide a new password or set send_email=true');
    }

    $token = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + 60 * 60);
    db_exec('UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL', array(now_utc(), $userId));
    db_exec(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        array($userId, $tokenHash, $expiresAt)
    );
    $resetUrl = password_reset_url($token);
    $appName = app_config('app_name', 'TagForge');
    $body = "Hello {$user['name']},\n\n"
        . "An administrator requested a password reset for your {$appName} account.\n\n"
        . "Open this link to choose a new password (valid for 1 hour):\n{$resetUrl}\n\n"
        . "— {$appName}\n";
    send_app_mail($user['email'], $appName . ' password reset', $body);
    $response = array('ok' => true, 'detail' => 'Reset link emailed', 'mode' => 'email');
    if (app_config('mail_debug', false)) {
        $response['reset_url'] = $resetUrl;
    }
    json_ok($response);
}

function handle_admin_impersonate($admin)
{
    ensure_admin_platform_schema();
    $data = request_json();
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : 0;
    $mode = isset($data['mode']) ? trim((string) $data['mode']) : 'readonly';
    if (!in_array($mode, array('readonly', 'timed'), true)) {
        json_error(422, 'mode must be readonly or timed');
    }
    $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : 30;
    if ($duration < 5) {
        $duration = 5;
    }
    if ($duration > 240) {
        $duration = 240;
    }
    if ($mode === 'readonly') {
        $duration = max($duration, 60);
    }

    $shop = get_shop($shopId);
    if ($shop['short_name'] === 'ADMIN') {
        json_error(422, 'Cannot open support view for the platform admin shop');
    }
    $owner = owner_for_shop($shopId);
    if (!$owner) {
        json_error(422, 'Shop has no owner account to view as');
    }

    $token = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
    $tokenHash = hash('sha256', $token);
    // Claim window: short-lived one-time link (10 minutes).
    $claimExpires = gmdate('Y-m-d H:i:s', time() + 10 * 60);
    db_exec(
        'INSERT INTO support_view_tokens
            (token_hash, admin_user_id, shop_id, target_user_id, mode, duration_minutes, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        array($tokenHash, (int) $admin['id'], $shopId, (int) $owner['id'], $mode, $duration, $claimExpires)
    );

    $url = support_view_url($token);
    json_ok(array(
        'shop_id' => $shopId,
        'mode' => $mode,
        'duration_minutes' => $duration,
        'claim_expires_at' => as_iso($claimExpires),
        'shop_url' => $url,
        'shop_name' => $shop['name'],
        'owner_email' => $owner['email'],
    ));
}

function handle_admin_edit_tenant_profile($admin)
{
    $data = request_json();
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : 0;
    $shop = get_shop($shopId);
    if ($shop['short_name'] === 'ADMIN') {
        json_error(422, 'Cannot edit the platform admin shop here');
    }

    $name = require_string($data, 'name', 2, 160);
    $phone = optional_string($data, 'phone_number', 20);
    $gst = optional_string($data, 'gst_no', 20);
    $address = optional_string($data, 'address', 255);
    $shortName = require_string($data, 'short_name', 2, 40);
    $prefix = strtoupper(require_string($data, 'tag_prefix', 1, 12));

    db_exec(
        'UPDATE shops SET name = ?, phone_number = ?, gst_no = ?, address = ?, short_name = ?, tag_prefix = ? WHERE id = ?',
        array($name, $phone, $gst, $address, $shortName, $prefix, $shopId)
    );

    json_ok(admin_tenant_response(get_shop($shopId)));
}

function handle_admin_list_notes($admin)
{
    ensure_admin_platform_schema();
    $shopId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    get_shop($shopId);
    $rows = db_all(
        'SELECT n.*, u.name AS author_name
         FROM shop_support_notes n
         LEFT JOIN users u ON u.id = n.author_user_id
         WHERE n.shop_id = ?
         ORDER BY n.created_at DESC, n.id DESC
         LIMIT 100',
        array($shopId)
    );
    json_ok(array_map('support_note_to_array', $rows));
}

function handle_admin_add_note($admin)
{
    ensure_admin_platform_schema();
    $data = request_json();
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : 0;
    get_shop($shopId);
    $body = require_string($data, 'body', 2, 1000);
    db_exec(
        'INSERT INTO shop_support_notes (shop_id, author_user_id, body) VALUES (?, ?, ?)',
        array($shopId, (int) $admin['id'], $body)
    );
    $note = db_one(
        'SELECT n.*, u.name AS author_name
         FROM shop_support_notes n
         LEFT JOIN users u ON u.id = n.author_user_id
         WHERE n.id = ?',
        array((int) db()->lastInsertId())
    );
    json_ok(support_note_to_array($note), 201);
}

function handle_admin_delete_note($admin)
{
    ensure_admin_platform_schema();
    $data = request_json();
    $noteId = isset($data['id']) ? (int) $data['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $note = db_one('SELECT * FROM shop_support_notes WHERE id = ?', array($noteId));
    if (!$note) {
        json_error(404, 'Note not found');
    }
    db_exec('DELETE FROM shop_support_notes WHERE id = ?', array($noteId));
    http_response_code(204);
    exit;
}

function handle_admin_send_owner_reset($admin)
{
    ensure_password_reset_schema();
    $data = request_json();
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : 0;
    get_shop($shopId);
    $owner = owner_for_shop($shopId);
    if (!$owner) {
        json_error(422, 'Shop has no owner account');
    }

    $token = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + 60 * 60);
    db_exec('UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL', array(now_utc(), (int) $owner['id']));
    db_exec(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        array((int) $owner['id'], $tokenHash, $expiresAt)
    );
    $resetUrl = password_reset_url($token);
    $appName = app_config('app_name', 'TagForge');
    $body = "Hello {$owner['name']},\n\n"
        . "TagForge support sent a password reset link for your shop account.\n\n"
        . "Open this link to choose a new password (valid for 1 hour):\n{$resetUrl}\n\n"
        . "If you did not request this, contact support.\n\n"
        . "— {$appName}\n";
    send_app_mail($owner['email'], $appName . ' password reset', $body);

    $response = array(
        'ok' => true,
        'detail' => 'Reset link emailed to ' . $owner['email'],
        'owner_email' => $owner['email'],
    );
    if (app_config('mail_debug', false)) {
        $response['reset_url'] = $resetUrl;
    }
    json_ok($response);
}

function handle_admin_export_tenants($admin)
{
    $shops = db_all(
        "SELECT s.*,
                u.name AS owner_name,
                u.email AS owner_email
         FROM shops s
         LEFT JOIN users u ON u.shop_id = s.id AND u.role = 'owner'
         WHERE COALESCE(s.short_name, '') <> 'ADMIN'
         ORDER BY s.name ASC, s.id ASC"
    );

    $filename = 'tagforge-tenants-' . gmdate('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, array(
        'id',
        'shop_name',
        'short_name',
        'tag_prefix',
        'address',
        'phone',
        'gst_no',
        'owner_name',
        'owner_email',
        'credits',
        'credits_expire_at',
        'unlimited_until',
        'is_active',
        'suspended_reason',
        'last_active_at',
        'tags_created',
        'total_prints',
        'reprints',
        'credits_used',
        'last_print_at',
        'created_at',
    ));

    foreach ($shops as $shop) {
        $usage = shop_usage_stats($shop['id']);
        fputcsv($out, array(
            $shop['id'],
            $shop['name'],
            $shop['short_name'],
            $shop['tag_prefix'],
            isset($shop['address']) ? $shop['address'] : '',
            isset($shop['phone_number']) ? $shop['phone_number'] : '',
            isset($shop['gst_no']) ? $shop['gst_no'] : '',
            $shop['owner_name'],
            $shop['owner_email'],
            (int) $shop['tag_credit_balance'],
            $shop['credits_expire_at'],
            $shop['unlimited_until'],
            !isset($shop['is_active']) || (int) $shop['is_active'] === 1 ? '1' : '0',
            isset($shop['suspended_reason']) ? $shop['suspended_reason'] : '',
            isset($shop['last_active_at']) ? $shop['last_active_at'] : '',
            $usage['tags_created'],
            $usage['total_prints'],
            $usage['reprints'],
            $usage['credits_used'],
            $usage['last_print_at'],
            $shop['created_at'],
        ));
    }
    fclose($out);
    exit;
}
