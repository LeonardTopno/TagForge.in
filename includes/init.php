<?php

$configFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI !== 'cli') {
        header('Location: install.php');
        exit;
    }
    throw new RuntimeException('Missing includes/config.php. Run install.php first.');
}

$GLOBALS['APP_CONFIG'] = require $configFile;

require_once __DIR__ . '/hosts.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = request_is_https();
    $cookieDomain = session_cookie_domain();
    // Sibling subdomains (tagforge.in / api.tagforge.in) are same-site, so Lax is enough.
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 60 * 60 * 12,
            'path' => '/',
            'domain' => $cookieDomain !== '' ? $cookieDomain : '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        session_set_cookie_params(60 * 60 * 12, '/; samesite=Lax', $cookieDomain, $secure, true);
    }
    session_name('tagprinter');
    // Drop legacy shared-domain cookie so host-only sessions can take over.
    if ($cookieDomain === '' && !empty($_SERVER['HTTP_HOST'])) {
        $host = strtolower(preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']));
        if ($host === 'tagforge.in' || substr($host, -11) === '.tagforge.in') {
            setcookie('tagprinter', '', array(
                'expires' => time() - 42000,
                'path' => '/',
                'domain' => '.tagforge.in',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        }
    }
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/razorpay.php';
require_once __DIR__ . '/platform.php';
require_once __DIR__ . '/admin_api.php';
require_once __DIR__ . '/ops_api.php';

csrf_token();
ensure_upload_dirs();
ensure_password_reset_schema();
ensure_admin_platform_schema();
ensure_ops_schema();
sync_billing_plans();
