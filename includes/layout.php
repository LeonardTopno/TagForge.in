<?php
if (!isset($pageScript)) {
    $pageScript = 'app.js';
}
if (!isset($pageTitle)) {
    $pageTitle = 'TagForge — Print tags. Run your shop.';
}
if (!isset($pageSurface)) {
    $pageSurface = $pageScript === 'admin.js' ? 'admin' : 'shop';
}
$csrf = csrf_token();
$boot = frontend_boot_config($pageScript, $pageSurface);
$assetRoot = dirname(__DIR__) . '/assets';
$faviconVersion = @filemtime($assetRoot . '/img/favicon.png') ?: time();
$faviconQuery = '?v=' . (string) $faviconVersion;
$cssVersion = @filemtime($assetRoot . '/css/app.css') ?: time();
$apiJsVersion = @filemtime($assetRoot . '/js/api.js') ?: time();
$pageJsVersion = @filemtime($assetRoot . '/js/' . $pageScript) ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <meta name="theme-color" content="#20313f">
  <meta name="format-detection" content="telephone=no, email=no, address=no">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="icon" href="assets/img/favicon.ico<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>" sizes="any">
  <link rel="icon" type="image/png" href="assets/img/favicon.png<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>" sizes="32x32">
  <link rel="apple-touch-icon" href="assets/img/favicon.png<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="shortcut icon" href="assets/img/favicon.ico<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css?v=<?php echo (int) $cssVersion; ?>">
</head>
<body class="surface-<?php echo htmlspecialchars($pageSurface, ENT_QUOTES, 'UTF-8'); ?>">
  <div id="app"></div>
  <script>
    window.CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
    window.APP_PAGE = <?php echo json_encode($boot['page']); ?>;
    window.APP_CONFIG = <?php echo json_encode($boot); ?>;
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <script src="assets/js/api.js?v=<?php echo (int) $apiJsVersion; ?>" defer></script>
  <script src="assets/js/<?php echo htmlspecialchars($pageScript, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $pageJsVersion; ?>" defer></script>
</body>
</html>
