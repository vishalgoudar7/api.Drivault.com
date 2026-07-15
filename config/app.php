<?php
declare(strict_types=1);

$basePath = '/' . trim(getenv('APP_BASE_PATH') ?: '', '/');

$localUrl = rtrim(getenv('APP_LOCAL_URL') ?: 'http://103.174.148.208', '/') . $basePath;
// $localUrl = rtrim(getenv('APP_LOCAL_URL') ?: 'http://localhost/New%20folder', '/') . $basePath;

$androidEmulatorUrl = rtrim(getenv('APP_ANDROID_EMULATOR_URL') ?: 'http://10.0.2.2', '/') . $basePath;

$productionUrl = rtrim(getenv('APP_PRODUCTION_URL') ?: 'https://api.drivault.com', '/') . $basePath;

$baseUrl = rtrim(getenv('APP_BASE_URL') ?: $localUrl, '/');

return [
    'base_path' => $basePath,
    'base_url' => $baseUrl,
    'local_url' => $localUrl,
    'android_emulator_url' => $androidEmulatorUrl,
    'production_url' => $productionUrl,
    'api_url' => $baseUrl . '/api',
];