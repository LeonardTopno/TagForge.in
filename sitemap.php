<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$shopUrl = rtrim(url_for_role('shop') !== '' ? url_for_role('shop') : app_base_url(), '/');
if ($shopUrl === '') {
    $shopUrl = rtrim(seo_absolute_url('/'), '/');
}

$now = gmdate('Y-m-d');
$urls = array(
    array('loc' => $shopUrl . '/', 'changefreq' => 'weekly', 'priority' => '1.0'),
);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $entry) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . $now . "</lastmod>\n";
    echo '    <changefreq>' . $entry['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $entry['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
