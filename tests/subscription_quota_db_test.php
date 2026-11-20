<?php

declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../api/includes/subscription-quota.php';

$result = $conn->query(
    "SELECT user_id, MIN(free_quota) AS expected_free_quota
     FROM subscriptions
     WHERE payment_status = 'Success'
       AND status IN ('active', 'expired')
     GROUP BY user_id
     ORDER BY MAX(id) DESC
     LIMIT 1"
);
$fixture = $result ? $result->fetch_assoc() : null;

if (!$fixture) {
    fwrite(STDERR, "SKIP: No successful subscription fixture found.\n");
    exit(0);
}

$resolved = resolveOriginalFreeQuota(
    $conn,
    (string) $fixture['user_id'],
    'upgrade',
    0,
    999
);
$expected = (int) $fixture['expected_free_quota'];

if ($resolved !== $expected) {
    fwrite(STDERR, "FAIL: Expected original free quota {$expected}, got {$resolved}.\n");
    exit(1);
}

echo "PASS: Missing upgrade subscription ID resolves securely to original free quota {$resolved}.\n";
