<?php
return array(
    'db_host' => 'localhost',
    'db_name' => 'tag_printer',
    'db_user' => 'tag_printer',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    'secret_key' => 'change-this-before-production',
    'tag_price_inr' => 0,
    'free_registration_credits' => 20,
    'free_registration_validity_days' => 2,
    'monthly_plan_price_inr' => 599,
    'app_name' => 'TagForge',
    'app_tagline' => 'Print tags. Run your shop.',
    // Canonical public URLs (TagForge production)
    'app_url' => 'https://tagforge.in',
    'urls' => array(
        'shop' => 'https://tagforge.in',
        'admin' => 'https://admin.tagforge.in',
        'app' => 'https://app.tagforge.in',
        'api' => 'https://api.tagforge.in',
    ),
    // Hostnames that map to each surface (all point at the same codebase/docroot)
    'hosts' => array(
        'shop' => array('tagforge.in', 'www.tagforge.in'),
        'admin' => array('admin.tagforge.in'),
        'app' => array('app.tagforge.in'),
        'api' => array('api.tagforge.in'),
    ),
    // Share login cookie across subdomains on HTTPS
    'cookie_domain' => '.tagforge.in',
    // Extra browser origins allowed to call the API with credentials (optional)
    'cors_origins' => array(),
    'mail_from' => 'noreply@tagforge.in',
    'mail_from_name' => 'TagForge',
    'mail_debug' => false,
    'razorpay_key_id' => '',
    'razorpay_key_secret' => '',
);
