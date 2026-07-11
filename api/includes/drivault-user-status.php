<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/drivault.php';

/**
 * Disable Drivault User
 */
function disableDrivaultUser(string $username): array
{
    $config = require __DIR__ . '/../../config/drivault.php';

    $url = rtrim($config['endpoint'], '/')
        . '/'
        . urlencode($username)
        . '/disable';

    return callDrivaultApi($url);
}

/**
 * Enable Drivault User
 */
function enableDrivaultUser(string $username): array
{
    $config = require __DIR__ . '/../../config/drivault.php';

    $url = rtrim($config['endpoint'], '/')
        . '/'
        . urlencode($username)
        . '/enable';

    return callDrivaultApi($url);
}

/**
 * Common API Function
 */
function callDrivaultApi(string $url): array
{
    $config = require __DIR__ . '/../../config/drivault.php';

    $curl = curl_init();

    curl_setopt_array($curl, [

        CURLOPT_URL => $url,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CUSTOMREQUEST => "PUT",

        CURLOPT_USERPWD =>
            $config['username'] . ":" . $config['password'],

        CURLOPT_HTTPHEADER => [

            "OCS-APIRequest: true",

            "Accept: application/json"

        ]

    ]);

    $response = curl_exec($curl);

    $error = curl_error($curl);

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($error) {

        return [

            'success' => false,

            'message' => $error

        ];
    }

    return [

        'success' => ($httpCode == 200),

        'http_code' => $httpCode,

        'response' => json_decode($response, true)

    ];
}