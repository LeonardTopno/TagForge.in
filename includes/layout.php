<?php
if (!isset($pageScript)) {
    $pageScript = 'app.js';
}
if (!isset($pageTitle)) {
    $pageTitle = 'Jewellery Tag Printer';
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <meta name="theme-color" content="#20313f">
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div id="app"></div>
  <script>
    window.CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
    window.APP_PAGE = <?php echo json_encode($pageScript === 'admin.js' ? 'admin' : 'shop'); ?>;
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/barcode.js"></script>
  <script src="assets/js/api.js"></script>
  <script src="assets/js/<?php echo htmlspecialchars($pageScript, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
