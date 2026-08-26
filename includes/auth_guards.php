<?php

/**
 * Polyfills for auth-critical helpers.
 * Keeps login working when public_html/includes is a partial/stale deploy.
 */

if (!function_exists('assert_shop_not_suspended')) {
    function assert_shop_not_suspended($user, $shop)
    {
        if (!$user || (isset($user['role']) && $user['role'] === 'admin')) {
            return;
        }
        if (isset($shop['is_active']) && (int) $shop['is_active'] === 0) {
            $reason = !empty($shop['suspended_reason']) ? $shop['suspended_reason'] : 'Contact support.';
            json_error(403, 'This shop is suspended. ' . $reason);
        }
    }
}

if (!function_exists('clear_support_view')) {
    function clear_support_view()
    {
        unset($_SESSION['support_view']);
    }
}

if (!function_exists('support_view_from_session')) {
    function support_view_from_session()
    {
        if (empty($_SESSION['support_view']) || !is_array($_SESSION['support_view'])) {
            return null;
        }
        return $_SESSION['support_view'];
    }
}

if (!function_exists('support_view_to_array')) {
    function support_view_to_array($view)
    {
        if (!$view) {
            return null;
        }
        return array(
            'admin_id' => isset($view['admin_id']) ? (int) $view['admin_id'] : null,
            'admin_name' => isset($view['admin_name']) ? $view['admin_name'] : null,
            'admin_email' => isset($view['admin_email']) ? $view['admin_email'] : null,
            'shop_id' => isset($view['shop_id']) ? (int) $view['shop_id'] : null,
            'mode' => isset($view['mode']) ? $view['mode'] : 'readonly',
            'expires_at' => isset($view['expires_at']) ? $view['expires_at'] : null,
        );
    }
}

if (!function_exists('touch_shop_activity')) {
    function touch_shop_activity($shopId)
    {
        try {
            if (function_exists('db_exec') && function_exists('now_utc')) {
                db_exec('UPDATE shops SET last_active_at = ? WHERE id = ?', array(now_utc(), (int) $shopId));
            }
        } catch (Exception $e) {
            // ignore
        }
    }
}

if (!function_exists('log_login_attempt')) {
    function log_login_attempt($email, $user, $success, $detail = null)
    {
        // Optional audit; never block auth when ops tables are missing.
    }
}

if (!function_exists('platform_public_payload')) {
    function platform_public_payload()
    {
        return array(
            'announcement' => null,
            'features' => array(
                'razorpay' => true,
                'registration' => true,
                'reprints' => true,
            ),
            'free_registration_credits' => (int) (function_exists('app_config') ? app_config('free_registration_credits', 20) : 20),
            'free_registration_validity_days' => max(1, (int) (function_exists('app_config') ? app_config('free_registration_validity_days', 2) : 2)),
        );
    }
}

if (!function_exists('feature_enabled')) {
    function feature_enabled($flag)
    {
        return true;
    }
}

if (!function_exists('admin_requires_2fa')) {
    function admin_requires_2fa($user)
    {
        return false;
    }
}

if (!function_exists('free_pack_credits')) {
    function free_pack_credits()
    {
        return (int) (function_exists('app_config') ? app_config('free_registration_credits', 20) : 20);
    }
}

if (!function_exists('free_pack_days')) {
    function free_pack_days()
    {
        return max(1, (int) (function_exists('app_config') ? app_config('free_registration_validity_days', 2) : 2));
    }
}

if (!function_exists('platform_setting')) {
    function platform_setting($key, $default = null)
    {
        return $default;
    }
}

if (!function_exists('auth_payload')) {
    function auth_payload($user, $shop)
    {
        return array(
            'access_token' => 'session',
            'token_type' => 'session',
            'user' => function_exists('user_to_array') ? user_to_array($user) : $user,
            'shop' => function_exists('shop_to_array') ? shop_to_array($shop) : $shop,
            'support' => support_view_to_array(support_view_from_session()),
            'platform' => platform_public_payload(),
            'csrf_token' => function_exists('csrf_token') ? csrf_token() : '',
        );
    }
}

if (!function_exists('login_user')) {
    function login_user($user)
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        clear_support_view();
        if (function_exists('csrf_token')) {
            csrf_token();
        }
        if (isset($user['role']) && $user['role'] !== 'admin') {
            touch_shop_activity($user['shop_id']);
        }
    }
}
