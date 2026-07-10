<?php

function disableUser(string $username): array
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://login.drivault.com/ocs/v1.php/cloud/users/' . urlencode($username) . '/disable',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => array(
            'OCS-APIRequest: true',
            'Accept: application/json',
            'Authorization: Basic YWRtaW46a3VSc2VmLWdvYm5vOC1nYW5rdXg='
        ),
    ));

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        return [
            'success' => false,
            'error' => curl_error($curl)
        ];
    }

    curl_close($curl);

    return [
        'success' => true,
        'response' => $response
    ];
}

function enableUser(string $username): array
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://login.drivault.com/ocs/v1.php/cloud/users/' . urlencode($username) . '/enable',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => array(
            'OCS-APIRequest: true',
            'Accept: application/json',
            'Authorization: Basic YWRtaW46a3VSc2VmLWdvYm5vOC1nYW5rdXg='
        ),
    ));

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        return [
            'success' => false,
            'error' => curl_error($curl)
        ];
    }

    curl_close($curl);

    return [
        'success' => true,
        'response' => $response
    ];
}
