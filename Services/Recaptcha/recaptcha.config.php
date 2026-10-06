<?php

require_once __DIR__ . '/../../Core/Env.php';
Env::load(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');

return [
    'site_key' => Env::get('RECAPTCHA_SITE_KEY', ''),
    'secret_key' => Env::get('RECAPTCHA_SECRET_KEY', ''),
    'version' => 'v2',
    'theme' => 'light',
    'size' => 'normal',
    'min_score' => 0.5,
    'language' => 'en',
    'test_mode' => false,
];
