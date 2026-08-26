<?php
/**
 * Mobile web entry for https://app.tagforge.in
 * Same shop SPA for now; native wrappers can point here later.
 */
require_once __DIR__ . '/includes/init.php';
enforce_host_role('app');
$pageTitle = 'TagForge App — Print tags. Run your shop.';
$pageDescription = 'TagForge mobile web app for jewellery hang-tag printing on TVS LP 46 NEO.';
$pageRobots = 'noindex, follow';
$pageScript = 'app.js';
$pageSurface = 'mobile';
require __DIR__ . '/includes/layout.php';
