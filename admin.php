<?php
require_once __DIR__ . '/includes/init.php';
enforce_host_role('admin');
$pageTitle = 'Admin Portal — TagForge';
$pageScript = 'admin.js';
$pageSurface = 'admin';
require __DIR__ . '/includes/layout.php';
