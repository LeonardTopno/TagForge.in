<?php
/**
 * One-shot migration: legacy FastAPI SQLite -> PHP MySQL.
 * Usage: php scripts/migrate_sqlite_to_mysql.php
 */

$root = dirname(__DIR__);
$sqlitePath = $root . DIRECTORY_SEPARATOR . 'legacy-fastapi-react' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tag_printer.db';

if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite database not found: {$sqlitePath}\n");
    exit(1);
}

require_once $root . '/includes/config.php';
$config = require $root . '/includes/config.php';

$mysql = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db_host'], $config['db_name'], $config['db_charset']),
    $config['db_user'],
    $config['db_pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

$sqlite = new PDO('sqlite:' . $sqlitePath, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

function table_count(PDO $pdo, $table)
{
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

function copy_logo($root, $logoPath)
{
    if ($logoPath === null || $logoPath === '') {
        return;
    }
    $src = $root . DIRECTORY_SEPARATOR . 'legacy-fastapi-react' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoPath);
    $dest = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoPath);
    if (!is_file($src)) {
        echo "  logo missing: {$src}\n";
        return;
    }
    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    copy($src, $dest);
    echo "  copied logo: {$logoPath}\n";
}

echo "Legacy SQLite: {$sqlitePath}\n";
echo "Target MySQL: {$config['db_host']}/{$config['db_name']}\n\n";

$tables = array(
    'shops',
    'users',
    'shop_items',
    'billing_plans',
    'jewellery_tags',
    'plan_purchases',
    'print_logs',
    'credit_ledger_entries',
);

foreach ($tables as $table) {
    echo sprintf("%-24s %d rows\n", $table . ':', table_count($sqlite, $table));
}

echo "\nClearing MySQL tables...\n";
$mysql->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (array_reverse($tables) as $table) {
    $mysql->exec('TRUNCATE TABLE ' . $table);
}
$mysql->exec('SET FOREIGN_KEY_CHECKS = 1');

$shopRows = $sqlite->query('SELECT * FROM shops ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$shopInsert = $mysql->prepare(
    'INSERT INTO shops (
        id, name, address, phone_number, gst_no, logo_path, short_name, tag_prefix, next_tag_number,
        tag_width_mm, tag_height_mm, font_size_pt, horizontal_offset_mm, vertical_offset_mm, show_shop_name,
        tag_credit_balance, credits_expire_at, unlimited_until, created_at
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($shopRows as $row) {
    $shopInsert->execute(array(
        $row['id'], $row['name'], $row['address'], $row['phone_number'], $row['gst_no'], $row['logo_path'],
        $row['short_name'], $row['tag_prefix'], $row['next_tag_number'],
        $row['tag_width_mm'], $row['tag_height_mm'], $row['font_size_pt'],
        $row['horizontal_offset_mm'], $row['vertical_offset_mm'], $row['show_shop_name'],
        $row['tag_credit_balance'], $row['credits_expire_at'], $row['unlimited_until'], $row['created_at'],
    ));
    copy_logo($root, $row['logo_path']);
}
echo 'Imported shops: ' . count($shopRows) . "\n";

$userRows = $sqlite->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$userInsert = $mysql->prepare(
    'INSERT INTO users (id, shop_id, name, email, password_hash, role, is_active, created_at)
     VALUES (?,?,?,?,?,?,?,?)'
);
foreach ($userRows as $row) {
    $userInsert->execute(array(
        $row['id'], $row['shop_id'], $row['name'], $row['email'], $row['password_hash'],
        strtolower($row['role']), $row['is_active'], $row['created_at'],
    ));
}
echo 'Imported users: ' . count($userRows) . "\n";

$itemRows = $sqlite->query('SELECT * FROM shop_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$itemInsert = $mysql->prepare(
    'INSERT INTO shop_items (id, shop_id, name, sort_order, created_at) VALUES (?,?,?,?,?)'
);
foreach ($itemRows as $row) {
    $itemInsert->execute(array($row['id'], $row['shop_id'], $row['name'], $row['sort_order'], $row['created_at']));
}
echo 'Imported shop_items: ' . count($itemRows) . "\n";

$planRows = $sqlite->query('SELECT * FROM billing_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$planInsert = $mysql->prepare(
    'INSERT INTO billing_plans (
        id, code, name, description, price_inr, tag_credits, validity_days, is_unlimited, is_active, sort_order, created_at, updated_at
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($planRows as $row) {
    $planInsert->execute(array(
        $row['id'], $row['code'], $row['name'], $row['description'], $row['price_inr'], $row['tag_credits'],
        $row['validity_days'], $row['is_unlimited'], $row['is_active'], $row['sort_order'], $row['created_at'], $row['updated_at'],
    ));
}
echo 'Imported billing_plans: ' . count($planRows) . "\n";

$tagRows = $sqlite->query('SELECT * FROM jewellery_tags ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$tagInsert = $mysql->prepare(
    'INSERT INTO jewellery_tags (
        id, shop_id, created_by, tag_number, item_name, category, purity, pieces, gross_weight, stone_weight,
        other_deduction, net_weight, copies, status, print_count, created_at, updated_at
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($tagRows as $row) {
    $tagInsert->execute(array(
        $row['id'], $row['shop_id'], $row['created_by'], $row['tag_number'], $row['item_name'], $row['category'],
        $row['purity'], $row['pieces'], $row['gross_weight'], $row['stone_weight'], $row['other_deduction'],
        $row['net_weight'], $row['copies'], strtolower($row['status']), $row['print_count'], $row['created_at'], $row['updated_at'],
    ));
}
echo 'Imported jewellery_tags: ' . count($tagRows) . "\n";

$purchaseRows = $sqlite->query('SELECT * FROM plan_purchases ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$purchaseInsert = $mysql->prepare(
    'INSERT INTO plan_purchases (
        id, shop_id, plan_id, amount_inr, tag_credits, validity_days, is_unlimited, status,
        razorpay_order_id, razorpay_payment_id, notes, created_at, paid_at
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($purchaseRows as $row) {
    $purchaseInsert->execute(array(
        $row['id'], $row['shop_id'], $row['plan_id'], $row['amount_inr'], $row['tag_credits'],
        $row['validity_days'], $row['is_unlimited'], strtolower($row['status']),
        $row['razorpay_order_id'], $row['razorpay_payment_id'], $row['notes'], $row['created_at'], $row['paid_at'],
    ));
}
echo 'Imported plan_purchases: ' . count($purchaseRows) . "\n";

$printRows = $sqlite->query('SELECT * FROM print_logs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$printInsert = $mysql->prepare(
    'INSERT INTO print_logs (id, shop_id, jewellery_tag_id, printed_by, copies, print_status, printed_at)
     VALUES (?,?,?,?,?,?,?)'
);
foreach ($printRows as $row) {
    $printInsert->execute(array(
        $row['id'], $row['shop_id'], $row['jewellery_tag_id'], $row['printed_by'], $row['copies'],
        strtolower($row['print_status']), $row['printed_at'],
    ));
}
echo 'Imported print_logs: ' . count($printRows) . "\n";

$ledgerRows = $sqlite->query('SELECT * FROM credit_ledger_entries ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$ledgerInsert = $mysql->prepare(
    'INSERT INTO credit_ledger_entries (
        id, shop_id, entry_type, credits, balance_after, description, jewellery_tag_id, purchase_id, created_at
    ) VALUES (?,?,?,?,?,?,?,?,?)'
);
foreach ($ledgerRows as $row) {
    $ledgerInsert->execute(array(
        $row['id'], $row['shop_id'], strtolower($row['entry_type']), $row['credits'], $row['balance_after'],
        $row['description'], $row['jewellery_tag_id'], $row['purchase_id'], $row['created_at'],
    ));
}
echo 'Imported credit_ledger_entries: ' . count($ledgerRows) . "\n";

foreach ($tables as $table) {
    $next = table_count($mysql, $table) + 1;
    $mysql->exec('ALTER TABLE ' . $table . ' AUTO_INCREMENT = ' . $next);
}

echo "\nMigration complete.\n\nAccounts:\n";
foreach ($mysql->query('SELECT u.email, u.role, s.name AS shop FROM users u JOIN shops s ON s.id = u.shop_id ORDER BY u.id') as $user) {
    echo "  {$user['email']} ({$user['role']}) — {$user['shop']}\n";
}
