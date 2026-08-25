<?php
$root = dirname(__DIR__);
$config = require $root . '/includes/config.php';

$email = isset($argv[1]) ? trim($argv[1]) : '';
$newPassword = isset($argv[2]) ? (string) $argv[2] : 'TagPrinter123';

if ($email === '') {
    fwrite(STDERR, "Usage: php scripts/reset_password.php <email> [new-password]\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db_host'], $config['db_name'], $config['db_charset']),
    $config['db_user'],
    $config['db_pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

$user = $pdo->prepare('SELECT id, email, name, role FROM users WHERE email = ?');
$user->execute(array(strtolower($email)));
$row = $user->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "No user found for {$email}\n");
    exit(1);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute(array($hash, $row['id']));

echo "Password reset for {$row['email']} ({$row['name']}, {$row['role']})\n";
echo "New password: {$newPassword}\n";
