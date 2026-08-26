<?php
require_once __DIR__ . '/includes/init.php';
enforce_host_role('admin');
$pageTitle = 'Admin Portal — TagForge';
$pageDescription = 'Secure TagForge admin portal for tenant support, billing, and platform operations.';
$pageRobots = 'noindex, nofollow, noarchive';
$pageScript = 'admin.js';
$pageSurface = 'admin';
require __DIR__ . '/includes/layout.php';
