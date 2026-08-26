<?php

if (!function_exists('app_config')) {
    function app_config($key, $default = null)
    {
        $config = isset($GLOBALS['APP_CONFIG']) ? $GLOBALS['APP_CONFIG'] : array();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }
}

function configured_urls()
{
    $urls = app_config('urls', array());
    if (!is_array($urls)) {
        $urls = array();
    }
    return array(
        'shop' => rtrim((string) (isset($urls['shop']) ? $urls['shop'] : app_config('app_url', '')), '/'),
        'admin' => rtrim((string) (isset($urls['admin']) ? $urls['admin'] : ''), '/'),
        'app' => rtrim((string) (isset($urls['app']) ? $urls['app'] : ''), '/'),
        'api' => rtrim((string) (isset($urls['api']) ? $urls['api'] : ''), '/'),
    );
}

function configured_hosts()
{
    $hosts = app_config('hosts', array());
    if (!is_array($hosts)) {
        $hosts = array();
    }
    $normalized = array(
        'shop' => array(),
        'admin' => array(),
        'app' => array(),
        'api' => array(),
    );
    foreach ($normalized as $role => $_) {
        $value = isset($hosts[$role]) ? $hosts[$role] : array();
        if (is_string($value) && $value !== '') {
            $value = array($value);
        }
        if (!is_array($value)) {
            $value = array();
        }
        foreach ($value as $host) {
            $host = strtolower(trim((string) $host));
            if ($host !== '') {
                $normalized[$role][] = $host;
            }
        }
    }
    return $normalized;
}

function current_http_host()
{
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $host = strtolower(trim(preg_replace('/:\d+$/', '', $host)));
    return $host;
}

function request_is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function host_role_for($host = null)
{
    $host = $host !== null ? strtolower(trim($host)) : current_http_host();
    if ($host === '') {
        return null;
    }
    foreach (configured_hosts() as $role => $hosts) {
        if (in_array($host, $hosts, true)) {
            return $role;
        }
    }
    return null;
}

function current_host_role()
{
    return host_role_for(current_http_host());
}

function url_for_role($role)
{
    $urls = configured_urls();
    $role = (string) $role;
    if (!empty($urls[$role])) {
        return $urls[$role];
    }
    if ($role === 'shop' && !empty($urls['shop'])) {
        return $urls['shop'];
    }
    return '';
}

function public_client_origins()
{
    $origins = array();
    foreach (configured_urls() as $url) {
        if ($url === '') {
            continue;
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            continue;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        $origins[] = $origin;
    }
    $extra = app_config('cors_origins', array());
    if (is_string($extra) && $extra !== '') {
        $extra = preg_split('/\s*,\s*/', $extra);
    }
    if (is_array($extra)) {
        foreach ($extra as $origin) {
            $origin = rtrim(trim((string) $origin), '/');
            if ($origin !== '') {
                $origins[] = $origin;
            }
        }
    }
    return array_values(array_unique($origins));
}

function apply_cors_headers()
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
    if ($origin === '') {
        return false;
    }
    $allowed = public_client_origins();
    if (!in_array($origin, $allowed, true)) {
        return false;
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
    return true;
}

function session_cookie_domain()
{
    $configured = trim((string) app_config('cookie_domain', ''));
    if ($configured !== '') {
        return $configured;
    }
    // Only share cookies across subdomains when a cross-host API URL is configured.
    // Host-only cookies are more reliable for admin.tagforge.in → same-host api.php.
    $urls = configured_urls();
    $apiUrl = isset($urls['api']) ? $urls['api'] : '';
    if ($apiUrl === '') {
        return '';
    }
    $host = current_http_host();
    $apiHost = parse_url($apiUrl, PHP_URL_HOST);
    if (!$host || !$apiHost || strtolower($apiHost) === $host) {
        return '';
    }
    if (substr($host, -11) === '.tagforge.in' || $host === 'tagforge.in') {
        return '.tagforge.in';
    }
    return '';
}

function redirect_to_url($url, $code = 302)
{
    header('Location: ' . $url, true, $code);
    exit;
}

function enforce_host_role($expectedRole)
{
    $role = current_host_role();
    if ($role === null) {
        return; // local / unknown host — no redirect
    }
    if ($role === $expectedRole) {
        return;
    }
    // API host should not render HTML shells.
    if ($role === 'api') {
        $apiUrl = url_for_role('api');
        redirect_to_url(($apiUrl !== '' ? $apiUrl : '') . '/api.php?r=health');
    }
    $target = url_for_role($expectedRole);
    if ($target === '') {
        return;
    }
    $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    // Keep query string when bouncing shop/app pages (e.g. password reset).
    if ($expectedRole === 'shop' || $expectedRole === 'app') {
        redirect_to_url(rtrim($target, '/') . $path);
    }
    redirect_to_url($target . '/');
}

function frontend_boot_config($pageScript = 'app.js', $surface = 'shop')
{
    $urls = configured_urls();
    $apiUrl = $urls['api'];
    $apiBase = $apiUrl !== '' ? $apiUrl : '';
    $crossApi = false;
    if ($apiBase !== '') {
        $currentHost = current_http_host();
        $apiHost = parse_url($apiBase, PHP_URL_HOST);
        $crossApi = $apiHost && $currentHost && strtolower($apiHost) !== $currentHost;
    }
    return array(
        'surface' => $surface,
        'page' => $pageScript === 'admin.js' ? 'admin' : 'shop',
        'appName' => app_config('app_name', 'TagForge'),
        'appTagline' => app_config('app_tagline', 'Print tags. Run your shop.'),
        'appVersion' => function_exists('app_version') ? app_version() : '0.0.0',
        'shopUrl' => $urls['shop'] !== '' ? $urls['shop'] : '',
        'adminUrl' => $urls['admin'] !== '' ? $urls['admin'] : '',
        'appUrl' => $urls['app'] !== '' ? $urls['app'] : '',
        'apiBaseUrl' => $crossApi ? rtrim($apiBase, '/') : '',
        'apiCredentials' => $crossApi ? 'include' : 'same-origin',
    );
}
