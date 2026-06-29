<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$drivaultConfig = require __DIR__ . '/../config/drivault.php';

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function extractDrivaultUsers(string $responseBody): array
{
    $data = json_decode($responseBody, true);

    if (!is_array($data)) {
        return [];
    }

    $users = $data['ocs']['data']['users'] ?? [];

    return is_array($users) ? array_values($users) : [];
}

function searchDrivaultUsers(string $endpoint, string $apiUsername, string $apiPassword, string $searchTerm): array
{
    $url = rtrim($endpoint, '/') . '?search=' . rawurlencode($searchTerm);
    $curlHandle = curl_init($url);

    curl_setopt_array($curlHandle, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $apiUsername . ':' . $apiPassword,
        CURLOPT_HTTPHEADER => [
            'OCS-APIRequest: true',
            'Accept: application/json',
        ],
    ]);

    $responseBody = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    $httpStatus = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
    curl_close($curlHandle);

    if ($responseBody === false) {
        throw new RuntimeException('Unable to connect to Drivault: ' . $curlError);
    }

    if ($httpStatus >= 400) {
        throw new RuntimeException('Drivault verification failed.');
    }

    return extractDrivaultUsers((string) $responseBody);
}

$email = trim((string) ($_POST['email'] ?? $_GET['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? $_GET['phone'] ?? ''));

if ($email === '' && $phone === '') {
    jsonResponse(422, [
        'success' => false,
        'message' => 'Email or mobile number is required.',
    ]);
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(422, [
        'success' => false,
        'message' => 'Invalid email address.',
    ]);
}

if ($phone !== '' && preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) !== 1) {
    jsonResponse(422, [
        'success' => false,
        'message' => 'Invalid mobile number.',
    ]);
}

$endpoint = trim((string) ($drivaultConfig['endpoint'] ?? ''));
$apiUsername = trim((string) ($drivaultConfig['username'] ?? ''));
$apiPassword = trim((string) ($drivaultConfig['password'] ?? ''));

if ($endpoint === '' || $apiUsername === '' || $apiPassword === '') {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Drivault API is not configured.',
    ]);
}

$searchTerm = $email !== ''
    ? $email
    : preg_replace('/[^0-9]/', '', $phone);
try {
    $users = searchDrivaultUsers(
    $endpoint,
    $apiUsername,
    $apiPassword,
    $searchTerm
);

if (!empty($users)) {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Drivault account found.',
        'matched_by' => $email !== '' ? 'email' : 'phone',
        'users' => $users,
    ]);
}
} catch (Throwable $exception) {
    jsonResponse(502, [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}

jsonResponse(404, [
    'success' => false,
    'message' => 'No Drivault account found with this email or mobile number.',
]);
