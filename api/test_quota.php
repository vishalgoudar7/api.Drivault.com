<?php
declare(strict_types=1);

/*
 * This legacy test endpoint duplicated the quota and payment logic now handled
 * by payment-success.php. Keeping that duplicate active could update a user's
 * quota without Razorpay signature verification.
 */

header('Content-Type: application/json');
http_response_code(410);

echo json_encode([
    'success' => false,
    'message' => 'This test endpoint is retired. Use payment-success.php.',
]);
