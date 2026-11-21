<?php

declare(strict_types=1);

use Razorpay\Api\Api;

/**
 * Cancel prior Razorpay Auto Pay mandates after a verified plan change.
 *
 * The new local subscription id is always excluded. Database state is changed
 * only after Razorpay reports a terminal, non-charging state.
 */
function cancelOldSubscription(mysqli $conn, string $userId, int $newSubscriptionId): array
{
    $result = [
        'success' => true,
        'user_id' => $userId,
        'new_subscription_id' => $newSubscriptionId,
        'cancelled' => [],
        'already_terminal' => [],
        'failed' => [],
    ];

    try {
        $stmt = $conn->prepare(
            "SELECT id, razorpay_subscription_id
             FROM subscriptions
             WHERE user_id = ?
               AND id <> ?
               AND payment_status = 'Success'
               AND razorpay_subscription_id IS NOT NULL
               AND TRIM(razorpay_subscription_id) <> ''"
        );
        $stmt->bind_param('si', $userId, $newSubscriptionId);
        $stmt->execute();
        $oldSubscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare(
            'SELECT razorpay_subscription_id FROM subscriptions WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->bind_param('is', $newSubscriptionId, $userId);
        $stmt->execute();
        $newSubscription = $stmt->get_result()->fetch_assoc();

        if (!$newSubscription) {
            throw new RuntimeException('The new subscription does not belong to the verified customer.');
        }

        $newRazorpayId = trim((string) ($newSubscription['razorpay_subscription_id'] ?? ''));
        $config = require dirname(__DIR__, 2) . '/config/razorpay.php';
        $api = new Api((string) $config['key_id'], (string) $config['key_secret']);
        $terminalStatuses = ['cancelled', 'completed', 'expired'];
        $cancellableStatuses = ['created', 'authenticated', 'active', 'pending', 'halted', 'paused'];

        foreach ($oldSubscriptions as $oldSubscription) {
            $localId = (int) $oldSubscription['id'];
            $razorpayId = trim((string) $oldSubscription['razorpay_subscription_id']);

            // Defence in depth for inconsistent/duplicate local data.
            if ($razorpayId === '' || ($newRazorpayId !== '' && hash_equals($newRazorpayId, $razorpayId))) {
                continue;
            }

            try {
                $remote = $api->subscription->fetch($razorpayId);
                $status = strtolower((string) ($remote['status'] ?? ''));

                if (in_array($status, $terminalStatuses, true)) {
                    if ($status === 'cancelled') {
                        markOldSubscriptionCancelled($conn, $userId, $localId, $razorpayId);
                    }
                    $result['already_terminal'][] = [
                        'subscription_id' => $localId,
                        'razorpay_subscription_id' => $razorpayId,
                        'status' => $status,
                    ];
                    continue;
                }

                if (!in_array($status, $cancellableStatuses, true)) {
                    throw new RuntimeException('Razorpay subscription is not in a cancellable state: ' . $status);
                }

                $cancelled = $remote->cancel(['cancel_at_cycle_end' => 0]);
                $finalStatus = strtolower((string) ($cancelled['status'] ?? ''));

                if ($finalStatus !== 'cancelled') {
                    $confirmed = $api->subscription->fetch($razorpayId);
                    $finalStatus = strtolower((string) ($confirmed['status'] ?? ''));
                }

                if ($finalStatus !== 'cancelled') {
                    throw new RuntimeException('Razorpay did not confirm immediate cancellation.');
                }

                markOldSubscriptionCancelled($conn, $userId, $localId, $razorpayId);
                $result['cancelled'][] = [
                    'subscription_id' => $localId,
                    'razorpay_subscription_id' => $razorpayId,
                    'status' => $finalStatus,
                ];
            } catch (Throwable $exception) {
                $result['success'] = false;
                $result['failed'][] = [
                    'subscription_id' => $localId,
                    'razorpay_subscription_id' => $razorpayId,
                    'message' => 'Unable to confirm cancellation.',
                ];
                error_log(sprintf(
                    '[cancel-old-subscription] Cancellation failed for local subscription %d / Razorpay subscription %s: %s',
                    $localId,
                    $razorpayId,
                    $exception->getMessage()
                ));
            }
        }
    } catch (Throwable $exception) {
        $result['success'] = false;
        $result['failed'][] = ['message' => 'Unable to inspect previous subscriptions.'];
        error_log('[cancel-old-subscription] Setup failed: ' . $exception->getMessage());
    }

    return $result;
}

function markOldSubscriptionCancelled(
    mysqli $conn,
    string $userId,
    int $localSubscriptionId,
    string $razorpaySubscriptionId
): void {
    $stmt = $conn->prepare(
        "UPDATE subscriptions
         SET status = 'cancelled', auto_renew = 0, renewal_mode = 'manual'
         WHERE id = ? AND user_id = ? AND razorpay_subscription_id = ?"
    );
    $stmt->bind_param('iss', $localSubscriptionId, $userId, $razorpaySubscriptionId);

    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to update the cancelled local subscription.');
    }
}
