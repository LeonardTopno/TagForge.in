<?php

function app_base_url()
{
    $shop = url_for_role('shop');
    if ($shop !== '') {
        return $shop;
    }
    $configured = trim((string) app_config('app_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scheme = request_is_https() ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }

    return $scheme . '://' . $host . $dir;
}

function password_reset_url($token)
{
    return app_base_url() . '/?reset=' . rawurlencode($token);
}

function send_app_mail($to, $subject, $bodyText)
{
    $fromEmail = trim((string) app_config('mail_from', 'noreply@localhost'));
    $fromName = trim((string) app_config('mail_from_name', app_config('app_name', 'Jewellery Tag Printer')));
    if ($fromEmail === '') {
        $fromEmail = 'noreply@localhost';
    }

    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . sprintf('%s <%s>', $fromName, $fromEmail),
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . PHP_VERSION,
    );

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $sent = @mail($to, $encodedSubject, $bodyText, implode("\r\n", $headers));

    if (!$sent || app_config('mail_debug', false)) {
        write_mail_outbox($to, $subject, $bodyText);
    }

    return (bool) $sent;
}

function write_mail_outbox($to, $subject, $bodyText)
{
    $dir = upload_root() . DIRECTORY_SEPARATOR . 'mail_outbox';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $safeTo = preg_replace('/[^A-Za-z0-9._@-]+/', '_', $to);
    $file = $dir . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . $safeTo . '.txt';
    $contents = "To: {$to}\nSubject: {$subject}\nSent-at: " . gmdate('c') . "\n\n{$bodyText}\n";
    @file_put_contents($file, $contents);
}
