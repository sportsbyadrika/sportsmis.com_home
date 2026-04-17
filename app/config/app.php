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
        'max_size'   => 5 * 1024 * 1024, // 5 MB
        'photo_size' => 2 * 1024 * 1024, // 2 MB
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
];
