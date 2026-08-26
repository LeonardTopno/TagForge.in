<?php

/**
 * Product semver — single source of truth for TagForge releases.
 * Deploy markers in api.php remain separate (cPanel / OPcache checks).
 */

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

if (!function_exists('app_version')) {
    function app_version()
    {
        return APP_VERSION;
    }
}
