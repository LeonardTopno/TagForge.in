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

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 60 * 60 * 12,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        session_set_cookie_params(60 * 60 * 12, '/', '', $secure, true);
    }
    session_name('tagprinter');
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/razorpay.php';

csrf_token();
ensure_upload_dirs();
ensure_password_reset_schema();
sync_billing_plans();
