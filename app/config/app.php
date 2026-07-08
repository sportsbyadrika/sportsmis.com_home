<?php
// Application configuration
return [
    'name'        => 'SportsMIS',
    'url'         => 'https://app.sportsmis.com',
    'home_url'    => 'https://sportsmis.com',
    'env'         => getenv('APP_ENV') ?: 'production',
    'debug'       => (getenv('APP_ENV') === 'local'),
    'timezone'    => 'Asia/Kolkata',
    'locale'      => 'en',
    'secret'      => getenv('APP_SECRET') ?: 'change-this-secret-key-in-production',

    'session' => [
        'name'     => 'sportsmis_session',
        'lifetime' => 7200,
    ],

    'upload' => [
        'path'       => __DIR__ . '/../public/assets/uploads/',
        'url'        => '/assets/uploads/',
        'max_size'   => 7 * 1024 * 1024, // 7 MB
        'photo_size' => 7 * 1024 * 1024, // 7 MB
        'allowed'    => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'img_only'   => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    'mail' => [
        'host'       => getenv('MAIL_HOST')     ?: 'smtp.gmail.com',
        'port'       => getenv('MAIL_PORT')     ?: 587,
        'username'   => getenv('MAIL_USERNAME') ?: '',
        'password'   => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@sportsmis.com',
        'from_name'  => getenv('MAIL_FROM_NAME') ?: 'SportsMIS',
    ],

    'google' => [
        'client_id'     => getenv('GOOGLE_CLIENT_ID')       ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET')   ?: '',
        'redirect_uri'  => getenv('GOOGLE_REDIRECT_URI')    ?: 'https://app.sportsmis.com/auth/google/callback',
    ],

    // Razorpay (ePayment). KEY_SECRET must NEVER appear in any file served
    // to the browser — it lives only in app/.env / cPanel env vars and is
    // read here on the server side.
    //   webhook_secret is a SEPARATE secret generated when registering the
    //   webhook URL in the Razorpay dashboard. It is used solely to verify
    //   the HMAC of incoming webhook callbacks — it is NOT used for
    //   Checkout signature verification (that uses key_secret).
    'razorpay' => [
        'key_id'         => getenv('RAZORPAY_KEY_ID')         ?: '',
        'key_secret'     => getenv('RAZORPAY_KEY_SECRET')     ?: '',
        'webhook_secret' => getenv('RAZORPAY_WEBHOOK_SECRET') ?: '',
    ],

    // CAPTCHA for public self-registration. Disabled until a provider and
    // both keys are set — leave provider empty to keep the honeypot /
    // timing / rate-limit defences only. Supported providers:
    //   'turnstile' — Cloudflare Turnstile (free, privacy-friendly)
    //   'recaptcha' — Google reCAPTCHA v2 checkbox
    // Set these via env vars (app/.env or cPanel), never hard-code secrets.
    'captcha' => [
        'provider'   => getenv('CAPTCHA_PROVIDER')   ?: '',
        'site_key'   => getenv('CAPTCHA_SITE_KEY')   ?: '',
        'secret_key' => getenv('CAPTCHA_SECRET_KEY') ?: '',
    ],
];
