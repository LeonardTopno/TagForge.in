<?php
/**
 * Local smoke test for auth dependency completeness.
 * Run: C:\xampp\php\php.exe scripts/verify_auth_deps.php
 */

$root = dirname(__DIR__);
$failures = array();

function require_defined($name, &$failures)
{
    if (!function_exists($name)) {
        $failures[] = "Missing function: {$name}";
    }
}

// Simulate a partial deploy: load core without platform.php first, then guards.
$configExample = $root . '/includes/config.example.php';
$configFile = $root . '/includes/config.php';
if (!is_file($configFile)) {
    // Build a throwaway config for local syntax/dependency checks only.
    $example = is_file($configExample) ? include $configExample : array();
    if (!is_array($example)) {
        $example = array();
    }
    $example['db_host'] = '127.0.0.1';
    $example['db_name'] = 'tagforge_test';
    $example['db_user'] = 'root';
    $example['db_pass'] = '';
    $GLOBALS['APP_CONFIG'] = $example;
} else {
    $GLOBALS['APP_CONFIG'] = require $configFile;
}

require_once $root . '/includes/hosts.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/schema.php';
require_once $root . '/includes/helpers.php';
require_once $root . '/includes/mail.php';
require_once $root . '/includes/razorpay.php';

// Intentionally skip platform.php here to prove auth_guards fill gaps.
require_once $root . '/includes/auth_guards.php';

$required = array(
    'assert_shop_not_suspended',
    'login_user',
    'auth_payload',
    'get_shop',
    'platform_public_payload',
    'feature_enabled',
    'log_login_attempt',
    'admin_requires_2fa',
    'clear_support_view',
    'support_view_from_session',
    'support_view_to_array',
    'touch_shop_activity',
    'csrf_token',
    'json_ok',
    'json_error',
    'require_email',
);

foreach ($required as $fn) {
    require_defined($fn, $failures);
}

// Exercise assert + payload without DB where possible.
$user = array(
    'id' => 1,
    'shop_id' => 1,
    'name' => 'Test',
    'email' => 'test@example.com',
    'role' => 'owner',
    'is_active' => 1,
    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
);
$shop = array(
    'id' => 1,
    'name' => 'Test Shop',
    'short_name' => 'TEST',
    'tag_prefix' => 'T',
    'next_tag_number' => 1,
    'tag_width_mm' => '64.00',
    'tag_height_mm' => '18.00',
    'font_size_pt' => '8.00',
    'horizontal_offset_mm' => '0.00',
    'vertical_offset_mm' => '0.00',
    'show_shop_name' => 1,
    'logo_path' => null,
    'tag_credit_balance' => 10,
    'credits_expire_at' => null,
    'unlimited_until' => null,
    'is_active' => 1,
    'suspended_reason' => null,
    'last_active_at' => null,
    'address' => '',
    'phone_number' => '',
    'gst_no' => '',
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    assert_shop_not_suspended($user, $shop);
} catch (Exception $e) {
    $failures[] = 'assert_shop_not_suspended threw: ' . $e->getMessage();
}

$payload = auth_payload($user, $shop);
if (!is_array($payload) || empty($payload['user']) || empty($payload['shop']) || empty($payload['platform'])) {
    $failures[] = 'auth_payload returned incomplete payload';
}
if (!password_verify('password123', $user['password_hash'])) {
    $failures[] = 'password_verify failed unexpectedly';
}

// Now load real platform and ensure it still works.
if (is_file($root . '/includes/platform.php')) {
    // platform_public_payload already defined by guards; real platform must use function_exists wrappers.
    // Re-including platform.php would fatal on redeclare — verify file parses instead.
    $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/includes/platform.php') . ' 2>&1');
    if ($lint === null || strpos($lint, 'No syntax errors') === false) {
        $failures[] = 'platform.php lint failed: ' . trim((string) $lint);
    }
}

$files = array(
    'api.php',
    'includes/init.php',
    'includes/helpers.php',
    'includes/platform.php',
    'includes/auth_guards.php',
    'includes/ops_api.php',
    'includes/admin_api.php',
);
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $failures[] = "Missing file: {$rel}";
        continue;
    }
    $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1');
    if ($lint === null || strpos($lint, 'No syntax errors') === false) {
        $failures[] = "Lint failed for {$rel}: " . trim((string) $lint);
    }
}

// Parse api.php handle_login for bare function calls that must exist.
$api = file_get_contents($root . '/api.php');
if (!preg_match('/function handle_login\(\)\s*\{(.*?)\nfunction /s', $api, $m)) {
    $failures[] = 'Could not locate handle_login in api.php';
} else {
    $body = $m[1];
    preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $body, $calls);
    $ignore = array(
        'isset' => true, 'empty' => true, 'unset' => true, 'array' => true,
        'function_exists' => true, 'password_verify' => true, 'is_string' => true,
        'trim' => true, 'strtolower' => true, 'intval' => true, 'strval' => true,
        'handle_login' => true,
        'if' => true, 'elseif' => true, 'else' => true, 'return' => true,
        'echo' => true, 'print' => true, 'exit' => true, 'die' => true,
        'catch' => true, 'try' => true, 'throw' => true, 'new' => true,
        // Optional 2FA helpers — only called behind function_exists / admin path.
        'create_email_otp' => true,
        'verify_email_otp' => true,
        'verify_totp_code' => true,
    );
    foreach (array_unique($calls[1]) as $call) {
        if (isset($ignore[$call])) {
            continue;
        }
        if (!function_exists($call)) {
            $failures[] = "handle_login calls undefined function: {$call}";
        }
    }
}

// Subprocess: full stack lint + ensure platform.php and helpers both define assert.
$php = PHP_BINARY;
$checkFull = <<<'PHP'
<?php
$root = $argv[1];
foreach (array('api.php','includes/init.php','includes/helpers.php','includes/platform.php','includes/auth_guards.php','includes/ops_api.php','includes/admin_api.php') as $rel) {
  $out = shell_exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$rel).' 2>&1');
  if (strpos((string)$out, 'No syntax errors') === false) { fwrite(STDERR, $rel.': '.$out); exit(1); }
}
$h = file_get_contents($root.'/includes/helpers.php');
$g = file_get_contents($root.'/includes/auth_guards.php');
$a = file_get_contents($root.'/api.php');
if (strpos($h, 'function assert_shop_not_suspended') === false) { fwrite(STDERR, "helpers missing assert\n"); exit(1); }
if (strpos($g, 'function assert_shop_not_suspended') === false) { fwrite(STDERR, "guards missing assert\n"); exit(1); }
if (strpos($a, 'auth_guards.php') === false) { fwrite(STDERR, "api.php does not load auth_guards\n"); exit(1); }
if (strpos($a, '2026-08-27-schema-column-check') === false) { fwrite(STDERR, "deploy marker missing\n"); exit(1); }
echo "FULL FILE CHECK OK\n";
PHP;
$tmp = tempnam(sys_get_temp_dir(), 'tfauth');
file_put_contents($tmp, $checkFull);
$fullOut = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($root) . ' 2>&1');
unlink($tmp);
if ($fullOut === null || strpos($fullOut, 'FULL FILE CHECK OK') === false) {
    $failures[] = 'Full file check failed: ' . trim((string) $fullOut);
}

if ($failures) {
    fwrite(STDERR, "AUTH DEP CHECK FAILED\n");
    foreach ($failures as $f) {
        fwrite(STDERR, " - {$f}\n");
    }
    exit(1);
}

echo "AUTH DEP CHECK OK\n";
echo "platform_public_payload: " . (function_exists('platform_public_payload') ? 'yes' : 'no') . "\n";
echo "assert_shop_not_suspended: " . (function_exists('assert_shop_not_suspended') ? 'yes' : 'no') . "\n";
echo "auth_payload keys: " . implode(',', array_keys($payload)) . "\n";
exit(0);
