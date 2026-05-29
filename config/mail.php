<?php
declare(strict_types=1);

$appConfig = require __DIR__ . '/app.php';

return [
    'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'port' => (int) (getenv('SMTP_PORT') ?: 587),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'username' => getenv('SMTP_USERNAME') ?: 'Vishalgoudar05@gmail.com',
    'password' => getenv('SMTP_PASSWORD') ?: 'cdnm zjot pxps dyhv',
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'Team Drivault',
    'inviter_name' => getenv('SMTP_INVITER_NAME') ?: 'Team Drivault',
    'website_url' => getenv('WEBSITE_URL') ?: $appConfig['base_url'],
    'support_email' => getenv('SUPPORT_EMAIL') ?: 'support@drivault.com',
    'google_play_link' => getenv('GOOGLE_PLAY_LINK') ?: 'https://play.google.com/store/apps/details?id=com.drivault&pcampaignid=web_share',
    'app_store_link' => getenv('APP_STORE_LINK') ?: 'https://www.apple.com/app-store/',
];
