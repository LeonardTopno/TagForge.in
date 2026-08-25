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
    'app_name' => 'Jewellery Tag Printer',
    // Optional public URL used in password-reset emails (auto-detected if blank).
    'app_url' => '',
    // From address for PHP mail() on shared hosting.
    'mail_from' => 'noreply@example.com',
    'mail_from_name' => 'Jewellery Tag Printer',
    // When true, forgot-password API also returns reset_url (useful for local testing).
    'mail_debug' => false,
    // Razorpay Dashboard → API Keys (use test keys first).
    'razorpay_key_id' => '',
    'razorpay_key_secret' => '',
);
