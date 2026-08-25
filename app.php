<?php
/**
 * Mobile web entry for https://app.tagforge.in
 * Same shop SPA for now; native wrappers can point here later.
 */
require_once __DIR__ . '/includes/init.php';
enforce_host_role('app');
$pageTitle = 'TagForge — Print tags. Run your shop.';
$pageScript = 'app.js';
$pageSurface = 'mobile';
require __DIR__ . '/includes/layout.php';
