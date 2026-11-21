<?php

declare(strict_types=1);

function subscriptionQuotaToGb(mixed $quota): int
{
    $value = strtoupper(trim((string) $quota));

    if (!preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(TB|GB)?/', $value, $matches)) {
        throw new InvalidArgumentException('Invalid storage quota.');
    }

    $gigabytes = (float) $matches[1];
    if (($matches[2] ?? 'GB') === 'TB') {
        $gigabytes *= 1024;
    }

    return (int) round($gigabytes);
}

function calculateSubscriptionTotalQuota(int $freeQuotaGb, mixed $planQuota): int
{
    return max(0, $freeQuotaGb) + subscriptionQuotaToGb($planQuota);
}

/**
 * Return the customer's original free allowance. Paid plan storage is never
 * carried into a replacement plan during an upgrade.
 */
function resolveOriginalFreeQuota(
    mysqli $conn,
    string $userId,
    string $mode,
    int $currentSubscriptionId,
    int $newCustomerQuotaGb
): int {
    if ($mode === 'new') {
        return max(0, $newCustomerQuotaGb);
    }

    if (!in_array($mode, ['upgrade', 'renew'], true)) {
        throw new InvalidArgumentException('A valid current subscription is required.');
    }

    // Older/cached checkout pages may not include subscription_id for an
    // upgrade. Resolve it from the verified customer's successful history
    // instead of trusting a client-only URL parameter.
    if ($currentSubscriptionId <= 0) {
        $stmt = $conn->prepare(
            "SELECT id
             FROM subscriptions
             WHERE user_id = ?
               AND payment_status = 'Success'
               AND status IN ('active', 'expired')
             ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, id DESC
             LIMIT 1"
        );
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $resolvedSubscription = $stmt->get_result()->fetch_assoc();
        $currentSubscriptionId = (int) ($resolvedSubscription['id'] ?? 0);
    }

    if ($currentSubscriptionId <= 0) {
        throw new InvalidArgumentException('A valid current subscription is required.');
    }

    $stmt = $conn->prepare(
        "SELECT id
         FROM subscriptions
         WHERE id = ?
           AND user_id = ?
           AND payment_status = 'Success'
         LIMIT 1"
    );
    $stmt->bind_param('is', $currentSubscriptionId, $userId);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new RuntimeException('The current subscription does not belong to this customer.');
    }

    // The first successful purchase captured the quota that existed before any
    // paid plan was applied. Later rows may contain an already-expanded total.
    $stmt = $conn->prepare(
        "SELECT free_quota
         FROM subscriptions
         WHERE user_id = ?
           AND payment_status = 'Success'
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $original = $stmt->get_result()->fetch_assoc();

    if (!$original || !is_numeric($original['free_quota'])) {
        throw new RuntimeException('Original free quota was not found.');
    }

    return max(0, (int) $original['free_quota']);
}
