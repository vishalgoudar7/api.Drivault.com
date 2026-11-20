<?php

declare(strict_types=1);

require __DIR__ . '/../api/includes/subscription-quota.php';

$failures = [];

function checkQuota(bool $condition, string $name): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures[] = $name;
    }
}

$freeQuota = 100;

checkQuota(calculateSubscriptionTotalQuota($freeQuota, '100GB') === 200, 'Free 100 + Lite 100 = 200');
checkQuota(calculateSubscriptionTotalQuota($freeQuota, '200GB') === 300, 'Upgrade replaces Lite: free 100 + Plus 200 = 300');
checkQuota(calculateSubscriptionTotalQuota($freeQuota, '300GB') === 400, 'Upgrade replaces Plus: free 100 + Pro 300 = 400');
checkQuota(calculateSubscriptionTotalQuota($freeQuota, '1TB') === 1124, 'TB plans are converted to GB');

exit($failures ? 1 : 0);
