<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$role = current_host_role();
$shopUrl = url_for_role('shop');
if ($shopUrl === '') {
    $shopUrl = app_base_url();
}
if ($shopUrl === '') {
    $shopUrl = seo_absolute_url('/', 'shop');
}
$sitemap = rtrim($shopUrl, '/') . '/sitemap.xml';

if ($role === 'admin' || $role === 'api') {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

if ($role === 'app') {
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /api.php\n";
    echo "Disallow: /admin.php\n";
    echo "Disallow: /install.php\n";
    echo "Disallow: /includes/\n";
    echo "Disallow: /uploads/\n";
    echo "Disallow: /scripts/\n";
    echo "Disallow: /docs/\n";
    echo "Disallow: /legacy-fastapi-react/\n";
    echo "Sitemap: {$sitemap}\n";
    exit;
}

// Shop / local
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /api.php\n";
echo "Disallow: /admin.php\n";
echo "Disallow: /app.php\n";
echo "Disallow: /install.php\n";
echo "Disallow: /includes/\n";
echo "Disallow: /uploads/\n";
echo "Disallow: /scripts/\n";
echo "Disallow: /docs/\n";
echo "Disallow: /legacy-fastapi-react/\n";
echo "Disallow: /sql/\n";
echo "Sitemap: {$sitemap}\n";
