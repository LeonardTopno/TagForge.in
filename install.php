<?php
$isInstalled = is_file(__DIR__ . '/includes/config.php');
$error = '';
$success = false;
$checks = array();

function install_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$checks[] = array('label' => 'PHP 7.4 or newer', 'ok' => $phpOk, 'detail' => 'Detected ' . PHP_VERSION);
$checks[] = array('label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'detail' => extension_loaded('pdo_mysql') ? 'Available' : 'Enable pdo_mysql in cPanel Select PHP Version');
$checks[] = array('label' => 'Sessions', 'ok' => function_exists('session_start'), 'detail' => 'Required for login');
$uploadsWritable = is_dir(__DIR__ . '/uploads') ? is_writable(__DIR__ . '/uploads') : is_writable(__DIR__);
$checks[] = array('label' => 'uploads/ writable', 'ok' => $uploadsWritable, 'detail' => $uploadsWritable ? 'OK' : 'Create uploads/logos and set permission 755 or 775');
$includesWritable = is_writable(__DIR__ . '/includes');
$checks[] = array('label' => 'includes/ writable', 'ok' => $includesWritable, 'detail' => $includesWritable ? 'OK' : 'Temporarily set includes/ to 775 so the installer can write config.php');

$form = array(
    'db_host' => isset($_POST['db_host']) ? $_POST['db_host'] : 'localhost',
    'db_name' => isset($_POST['db_name']) ? $_POST['db_name'] : 'tag_printer',
    'db_user' => isset($_POST['db_user']) ? $_POST['db_user'] : '',
    'db_pass' => isset($_POST['db_pass']) ? $_POST['db_pass'] : '',
    'tag_price_inr' => isset($_POST['tag_price_inr']) ? $_POST['tag_price_inr'] : '0',
    'free_registration_credits' => isset($_POST['free_registration_credits']) ? $_POST['free_registration_credits'] : '20',
    'free_registration_validity_days' => isset($_POST['free_registration_validity_days']) ? $_POST['free_registration_validity_days'] : '2',
    'monthly_plan_price_inr' => isset($_POST['monthly_plan_price_inr']) ? $_POST['monthly_plan_price_inr'] : '599',
    'admin_name' => isset($_POST['admin_name']) ? $_POST['admin_name'] : '',
    'admin_email' => isset($_POST['admin_email']) ? $_POST['admin_email'] : '',
    'admin_password' => isset($_POST['admin_password']) ? $_POST['admin_password'] : '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    if ($isInstalled) {
        $error = 'Already installed. Delete includes/config.php only if you intend to reinstall.';
    } else {
        $canInstall = $phpOk && extension_loaded('pdo_mysql') && $includesWritable;
        if (!$canInstall) {
            $error = 'Fix the failed environment checks before installing.';
        } else {
            try {
                $dsn = 'mysql:host=' . $form['db_host'] . ';charset=utf8mb4';
                $pdo = new PDO($dsn, $form['db_user'], $form['db_pass'], array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ));
                $dbName = trim($form['db_name']);
                if (!preg_match('/^[A-Za-z0-9_-]+$/', $dbName)) {
                    throw new Exception('Database name can only contain letters, numbers, underscore, and hyphen.');
                }
                $quoted = str_replace('`', '``', $dbName);
                try {
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $quoted . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                } catch (Exception $ignored) {
                    // Shared hosting often already created the database in cPanel.
                }
                $pdo->exec('USE `' . $quoted . '`');
                require_once __DIR__ . '/includes/schema.php';
                install_schema($pdo);
                seed_billing_plans($pdo);

                $adminEmail = strtolower(trim($form['admin_email']));
                if ($adminEmail !== '') {
                    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Admin email is not valid.');
                    }
                    if (strlen($form['admin_password']) < 8) {
                        throw new Exception('Admin password must be at least 8 characters.');
                    }
                    $adminName = trim($form['admin_name']);
                    if ($adminName === '') {
                        $adminName = 'Admin';
                    }
                    $pdo->beginTransaction();
                    $pdo->prepare('INSERT INTO shops (name, short_name, tag_credit_balance) VALUES (?, ?, ?)')
                        ->execute(array('Platform Admin', 'ADMIN', 0));
                    $shopId = (int) $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO users (shop_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
                        ->execute(array(
                            $shopId,
                            $adminName,
                            $adminEmail,
                            password_hash($form['admin_password'], PASSWORD_DEFAULT),
                            'admin',
                        ));
                    seed_items_for_shop($pdo, $shopId);
                    $pdo->commit();
                }

                if (!is_dir(__DIR__ . '/uploads/logos')) {
                    mkdir(__DIR__ . '/uploads/logos', 0755, true);
                }

                $secret = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
                $config = array(
                    'db_host' => $form['db_host'],
                    'db_name' => $dbName,
                    'db_user' => $form['db_user'],
                    'db_pass' => $form['db_pass'],
                    'db_charset' => 'utf8mb4',
                    'secret_key' => $secret,
                    'tag_price_inr' => max(0, (int) $form['tag_price_inr']),
                    'free_registration_credits' => max(0, (int) $form['free_registration_credits']),
                    'free_registration_validity_days' => max(1, (int) $form['free_registration_validity_days']),
                    'monthly_plan_price_inr' => max(1, (int) $form['monthly_plan_price_inr']),
                    'app_name' => 'Jewellery Tag Printer',
                    'razorpay_key_id' => '',
                    'razorpay_key_secret' => '',
                );
                $written = file_put_contents(
                    __DIR__ . '/includes/config.php',
                    "<?php\nreturn " . var_export($config, true) . ";\n"
                );
                if ($written === false) {
                    throw new Exception('Could not write includes/config.php. Check folder permissions.');
                }
                $isInstalled = true;
                $success = true;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install Jewellery Tag Printer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #20313f; min-height: 100vh; }
    .install-card { max-width: 760px; }
    .brand-mark {
      width: 42px; height: 42px; border-radius: 8px; background: #f5c451;
      display: inline-flex; align-items: center; justify-content: center; font-weight: 800; color: #173042;
    }
  </style>
</head>
<body class="d-flex align-items-center py-5">
  <div class="container">
    <div class="card shadow install-card mx-auto">
      <div class="card-body p-4 p-md-5">
        <div class="d-flex gap-3 align-items-start mb-4">
          <span class="brand-mark">JT</span>
          <div>
            <h1 class="h3 mb-1">Jewellery Tag Printer</h1>
            <p class="text-secondary mb-0">PHP, MySQL, HTML, CSS, JavaScript, and Bootstrap installer for shared hosting.</p>
          </div>
        </div>

        <?php if ($success): ?>
          <div class="alert alert-success">Installation completed.</div>
          <ol>
            <li>Delete <code>install.php</code> from the server.</li>
            <li>Open the <a href="index.php">shop app</a> and register a shop, or sign in to the <a href="admin.php">admin portal</a>.</li>
          </ol>
        <?php elseif ($isInstalled && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
          <div class="alert alert-info">This copy is already installed. Open the <a href="index.php">shop app</a> or <a href="admin.php">admin portal</a>.</div>
        <?php else: ?>
          <?php if ($error): ?><div class="alert alert-danger"><?php echo install_h($error); ?></div><?php endif; ?>

          <h2 class="h5">Environment</h2>
          <ul class="list-group mb-4">
            <?php foreach ($checks as $check): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><?php echo install_h($check['label']); ?> <small class="text-secondary"><?php echo install_h($check['detail']); ?></small></span>
                <span class="badge <?php echo $check['ok'] ? 'text-bg-success' : 'text-bg-danger'; ?>"><?php echo $check['ok'] ? 'OK' : 'Fix'; ?></span>
              </li>
            <?php endforeach; ?>
          </ul>

          <form method="post" class="row g-3">
            <h2 class="h5">MySQL</h2>
            <p class="text-secondary small mb-0">Create the database and user in cPanel first, then paste the details here. Host is usually <code>localhost</code>.</p>
            <div class="col-md-6">
              <label class="form-label">Database host</label>
              <input class="form-control" name="db_host" value="<?php echo install_h($form['db_host']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Database name</label>
              <input class="form-control" name="db_name" value="<?php echo install_h($form['db_name']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Database user</label>
              <input class="form-control" name="db_user" value="<?php echo install_h($form['db_user']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Database password</label>
              <input class="form-control" type="password" name="db_pass" value="<?php echo install_h($form['db_pass']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Free tags on shop registration</label>
              <input class="form-control" type="number" min="0" name="free_registration_credits" value="<?php echo install_h($form['free_registration_credits']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Free pack validity (days)</label>
              <input class="form-control" type="number" min="1" name="free_registration_validity_days" value="<?php echo install_h($form['free_registration_validity_days']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Monthly unlimited price (INR)</label>
              <input class="form-control" type="number" min="1" name="monthly_plan_price_inr" value="<?php echo install_h($form['monthly_plan_price_inr']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Rupees per tag (display, optional)</label>
              <input class="form-control" type="number" min="0" name="tag_price_inr" value="<?php echo install_h($form['tag_price_inr']); ?>">
            </div>

            <h2 class="h5 mt-3">Admin portal account (optional)</h2>
            <p class="text-secondary small mb-0">Leave blank to skip. You can add an admin user later in phpMyAdmin by setting a user <code>role</code> to <code>admin</code>.</p>
            <div class="col-md-4">
              <label class="form-label">Admin name</label>
              <input class="form-control" name="admin_name" value="<?php echo install_h($form['admin_name']); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Admin email</label>
              <input class="form-control" type="email" name="admin_email" value="<?php echo install_h($form['admin_email']); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Admin password</label>
              <input class="form-control" type="password" name="admin_password" minlength="8" value="<?php echo install_h($form['admin_password']); ?>">
            </div>
            <div class="col-12">
              <button class="btn btn-success" type="submit" name="install" value="1">Create tables and finish install</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <p class="text-center text-white-50 mt-3 mb-0">Migids Software LLP, Bengaluru</p>
  </div>
</body>
</html>
