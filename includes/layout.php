<?php
if (!isset($pageScript)) {
    $pageScript = 'app.js';
}
if (!isset($pageSurface)) {
    $pageSurface = $pageScript === 'admin.js' ? 'admin' : 'shop';
}
if (!isset($pageTitle)) {
    $pageTitle = '';
}
if (!isset($pageDescription)) {
    $pageDescription = '';
}
if (!isset($pageRobots)) {
    $pageRobots = '';
}

require_once __DIR__ . '/seo.php';

$seo = seo_build_meta(array(
    'surface' => $pageSurface,
    'title' => $pageTitle,
    'description' => $pageDescription,
    'robots' => $pageRobots,
));
$pageTitle = $seo['title'];

$csrf = csrf_token();
$boot = frontend_boot_config($pageScript, $pageSurface);
$boot['seo'] = array(
    'title' => $seo['title'],
    'indexable' => !empty($seo['indexable']),
);
$assetRoot = dirname(__DIR__) . '/assets';
$faviconVersion = @filemtime($assetRoot . '/img/favicon.png') ?: time();
$faviconQuery = '?v=' . (string) $faviconVersion;
$cssVersion = @filemtime($assetRoot . '/css/app.css') ?: time();
$apiJsVersion = @filemtime($assetRoot . '/js/api.js') ?: time();
$pageJsVersion = @filemtime($assetRoot . '/js/' . $pageScript) ?: time();
$barcodeJsVersion = @filemtime($assetRoot . '/js/barcode.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <meta name="theme-color" content="#20313f">
  <meta name="format-detection" content="telephone=no, email=no, address=no">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <?php echo seo_render_head_tags($seo); ?>
  <script>
    (function () {
      document.documentElement.classList.add('js');
      function markReady() {
        document.documentElement.classList.add('app-ready');
      }
      // Failsafe: never trap users if assets fail to update after a PHP-only deploy.
      setTimeout(markReady, 6000);
      function watchApp() {
        var app = document.getElementById('app');
        if (!app) {
          markReady();
          return;
        }
        function maybeReady() {
          // Keep splash while SEO landing is still the only placeholder, or #app is empty.
          if (app.querySelector('.seo-landing')) return false;
          if (!app.children.length) return false;
          markReady();
          return true;
        }
        if (maybeReady()) return;
        if (!window.MutationObserver) return;
        var obs = new MutationObserver(function () {
          if (maybeReady()) obs.disconnect();
        });
        obs.observe(app, { childList: true, subtree: true });
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', watchApp);
      } else {
        watchApp();
      }
    })();
  </script>
  <style>
    /* Hide SEO fallback only while splash is up; if JS assets never boot, failsafe
       adds app-ready and the crawlable landing becomes visible again. */
    html.js:not(.app-ready) .seo-landing { display: none !important; }
    .boot-splash { display: none; }
    html.js:not(.app-ready) .boot-splash {
      display: grid;
      place-items: center;
      position: fixed;
      inset: 0;
      z-index: 10000;
      margin: 0;
      background: #20313f;
      color: #f8fafc;
      font-family: Inter, ui-sans-serif, system-ui, "Segoe UI", sans-serif;
      text-align: center;
      gap: 8px;
    }
    html.js:not(.app-ready) .boot-splash strong {
      font-size: 1.35rem;
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    html.js:not(.app-ready) .boot-splash span {
      color: #c4b08a;
      font-size: 0.95rem;
    }
  </style>
  <link rel="icon" href="assets/img/favicon.ico<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>" sizes="any">
  <link rel="icon" type="image/png" href="assets/img/favicon.png<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>" sizes="32x32">
  <link rel="apple-touch-icon" href="assets/img/favicon.png<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="shortcut icon" href="assets/img/favicon.ico<?php echo htmlspecialchars($faviconQuery, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css?v=<?php echo (int) $cssVersion; ?>">
</head>
<body class="surface-<?php echo htmlspecialchars($pageSurface, ENT_QUOTES, 'UTF-8'); ?>">
  <div id="boot-splash" class="boot-splash" aria-hidden="true">
    <strong><?php echo htmlspecialchars(isset($seo['app_name']) ? $seo['app_name'] : 'TagForge', ENT_QUOTES, 'UTF-8'); ?></strong>
    <span><?php echo htmlspecialchars(isset($seo['tagline']) ? $seo['tagline'] : 'Print tags. Run your shop.', ENT_QUOTES, 'UTF-8'); ?></span>
  </div>
  <div id="app"><?php
    // Crawlable fallback for public shop landing (replaced by the SPA after boot).
    if (!empty($seo['indexable'])) {
        echo seo_crawlable_landing_html($seo);
    }
  ?></div>
  <noscript>
    <div class="seo-noscript">
      <h1><?php echo htmlspecialchars($seo['app_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p><?php echo htmlspecialchars($seo['description'], ENT_QUOTES, 'UTF-8'); ?></p>
      <p>JavaScript is required to sign in and print jewellery tags.</p>
    </div>
  </noscript>
  <script>
    window.CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
    window.APP_PAGE = <?php echo json_encode($boot['page']); ?>;
    window.APP_CONFIG = <?php echo json_encode($boot); ?>;
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <?php if ($pageScript === 'app.js') { ?>
  <script src="assets/js/barcode.js?v=<?php echo (int) $barcodeJsVersion; ?>" defer></script>
  <?php } ?>
  <script src="assets/js/api.js?v=<?php echo (int) $apiJsVersion; ?>" defer></script>
  <script src="assets/js/<?php echo htmlspecialchars($pageScript, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) $pageJsVersion; ?>" defer></script>
</body>
</html>
