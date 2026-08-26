<?php
require_once __DIR__ . '/includes/init.php';
enforce_host_role('shop');
$pageTitle = 'TagForge — Print tags. Run your shop.';
$pageDescription = 'TagForge is jewellery hang-tag software for Indian jewellery shops. Create barcode tags, print on TVS LP 46 NEO (80×18 mm), manage credits, and run your shop from the browser.';
$pageScript = 'app.js';
$pageSurface = 'shop';
require __DIR__ . '/includes/layout.php';
