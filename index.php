<?php
require_once __DIR__ . '/includes/init.php';
enforce_host_role('shop');
$pageTitle = 'TagForge — Print tags. Run your shop.';
$pageScript = 'app.js';
$pageSurface = 'shop';
require __DIR__ . '/includes/layout.php';
