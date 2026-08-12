<?php

declare(strict_types=1);

require '../config/db.php';
require '../vendor/autoload.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

$logFile = __DIR__ . '/payment-debug.log';
$config = require '../config/razorpay.php';

function razorpayWebhookLog(string $message, array $context = []): void
{
    global $logFile;

    file_put_contents(
        $logFile,
        '[razorpay-webhook] ' . date('Y-m-d H:i:s') . ' ' . $message .
        ($context ? ' ' . json_encode($context) : '') .
        PHP_EOL,
        FILE_APPEND
    );
}

function razorpayWebhookResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function handleSubscriptionCancelled(mysqli $conn, array $payload): void
{
    $payment = $payload['payload']['payment']['entity'] ?? [];
    $subscriptionEntity = $payload['payload']['subscription']['entity'] ?? [];
    $razorpaySubscriptionId = trim((string) ($subscriptionEntity['id'] ?? $payment['subscription_id'] ?? ''));

    razorpayWebhookLog('Subscription cancelled webhook received', [
        'razorpay_subscription_id' => $razorpaySubscriptionId,
    ]);

    if ($razorpaySubscriptionId === '') {
        razorpayWebhookResponse(422, [
            'success' => false,
            'message' => 'Missing Razorpay subscription id.',
        ]);
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT id FROM subscriptions WHERE razorpay_subscription_id = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $razorpaySubscriptionId);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc();

        if (!$subscription) {
            throw new RuntimeException('Subscription not found.');
        }

        $stmt = $conn->prepare(
            "UPDATE subscriptions
             SET status='cancelled',
                 auto_renew=0,
                 renewal_mode='manual',
                 updated_at=NOW()
             WHERE id=?"
        );
        $subscriptionId = (int) $subscription['id'];
        $stmt->bind_param('i', $subscriptionId);

        if (!$stmt->execute()) {
            throw new RuntimeException('Unable to mark subscription cancelled: ' . $stmt->error);
        }

        $conn->commit();

        razorpayWebhookLog('Subscription marked cancelled', [
            'subscription_id' => $subscriptionId,
            'razorpay_subscription_id' => $razorpaySubscriptionId,
        ]);

        razorpayWebhookResponse(200, [
            'success' => true,
            'message' => 'Subscription cancelled webhook processed.',
            'subscription_id' => $subscriptionId,
        ]);
    } catch (Throwable $exception) {
        try {
            $conn->rollback();
        } catch (Throwable) {
        }

        razorpayWebhookLog('Subscription cancelled webhook failed', [
            'error' => $exception->getMessage(),
            'razorpay_subscription_id' => $razorpaySubscriptionId,
        ]);

        razorpayWebhookResponse(500, [
            'success' => false,
            'message' => $exception->getMessage(),
        ]);
    }
}

function handleSubscriptionPaymentIssue(mysqli $conn, array $payload, string $event): void
{
    $payment = $payload['payload']['payment']['entity'] ?? [];
    $subscriptionEntity = $payload['payload']['subscription']['entity'] ?? [];
    $razorpaySubscriptionId = trim((string) ($subscriptionEntity['id'] ?? $payment['subscription_id'] ?? ''));
    $paymentId = trim((string) ($payment['id'] ?? ''));
    $orderId = trim((string) ($payment['order_id'] ?? ''));
    $amount = isset($payment['amount']) && is_numeric($payment['amount'])
        ? ((float) $payment['amount'] / 100)
        : 0.0;
    $isHalted = $event === 'subscription.halted';

    razorpayWebhookLog('Subscription payment issue webhook received', [
        'event' => $event,
        'razorpay_subscription_id' => $razorpaySubscriptionId,
        'payment_id' => $paymentId,
    ]);

    if ($razorpaySubscriptionId === '') {
        razorpayWebhookResponse(422, [
            'success' => false,
            'message' => 'Missing Razorpay subscription id.',
        ]);
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT * FROM subscriptions WHERE razorpay_subscription_id = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $razorpaySubscriptionId);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc();

        if (!$subscription) {
            throw new RuntimeException('Subscription not found.');
        }

        $subscriptionId = (int) $subscription['id'];
        $autoRenew = $isHalted ? 0 : 1;
        $renewalMode = $isHalted ? 'manual' : 'auto';

        $stmt = $conn->prepare(
            "UPDATE subscriptions
             SET status='pending',
                 payment_status='Failed',
                 renewal_mode=?,
                 auto_renew=?,
                 razorpay_order_id=COALESCE(NULLIF(?, ''), razorpay_order_id),
                 razorpay_payment_id=COALESCE(NULLIF(?, ''), razorpay_payment_id),
                 updated_at=NOW(),
                 disabled_at=NULL
             WHERE id=?"
        );
        $stmt->bind_param('sissi', $renewalMode, $autoRenew, $orderId, $paymentId, $subscriptionId);

        if (!$stmt->execute()) {
            throw new RuntimeException('Unable to update subscription payment issue: ' . $stmt->error);
        }

        $paymentInsertId = null;

        if ($paymentId !== '') {
            $stmt = $conn->prepare('SELECT id FROM payments WHERE razorpay_payment_id = ? AND status = ? LIMIT 1');
            $paymentStatus = 'failed';
            $stmt->bind_param('ss', $paymentId, $paymentStatus);
            $stmt->execute();
            $existingPayment = $stmt->get_result()->fetch_assoc();

            if (!$existingPayment) {
                $paymentType = 'renewal';
                $rawResponse = json_encode($payload);
                $paymentAmount = $amount > 0 ? $amount : (float) ($subscription['paid_amount'] ?? 0);
                $userId = (string) $subscription['user_id'];

                $stmt = $conn->prepare(
                    'INSERT INTO payments
                    (
                        user_id,
                        subscription_id,
                        razorpay_order_id,
                        razorpay_subscription_id,
                        razorpay_payment_id,
                        amount,
                        payment_type,
                        status,
                        response
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )'
                );
                $stmt->bind_param(
                    'sisssdsss',
                    $userId,
                    $subscriptionId,
                    $orderId,
                    $razorpaySubscriptionId,
                    $paymentId,
                    $paymentAmount,
                    $paymentType,
                    $paymentStatus,
                    $rawResponse
                );

                if (!$stmt->execute()) {
                    throw new RuntimeException('Unable to save failed payment: ' . $stmt->error);
                }

                $paymentInsertId = $conn->insert_id;
            }
        }

        $conn->commit();

        razorpayWebhookLog('Subscription payment issue processed', [
            'event' => $event,
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'payment_insert_id' => $paymentInsertId,
            'auto_renew' => $autoRenew,
        ]);

        razorpayWebhookResponse(200, [
            'success' => true,
            'message' => $event . ' webhook processed.',
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'auto_renew' => $autoRenew,
        ]);
    } catch (Throwable $exception) {
        try {
            $conn->rollback();
        } catch (Throwable) {
        }

        razorpayWebhookLog('Subscription payment issue webhook failed', [
            'event' => $event,
            'error' => $exception->getMessage(),
            'razorpay_subscription_id' => $razorpaySubscriptionId,
        ]);

        razorpayWebhookResponse(500, [
            'success' => false,
            'message' => $exception->getMessage(),
        ]);
    }
}

$api = new Api(
    $config['key_id'],
    $config['key_secret']
);

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if ($payload === '') {
    razorpayWebhookResponse(400, [
        'success' => false,
        'message' => 'Empty payload',
    ]);
}

try {
    $api->utility->verifyWebhookSignature(
        $payload,
        $signature,
        $config['webhook_secret']
    );
} catch (Throwable $exception) {
    razorpayWebhookLog('Invalid webhook signature', [
        'error' => $exception->getMessage(),
    ]);

    razorpayWebhookResponse(401, [
        'success' => false,
        'message' => 'Invalid webhook signature',
    ]);
}

$data = json_decode((string) $payload, true);

if (!is_array($data)) {
    razorpayWebhookResponse(400, [
        'success' => false,
        'message' => 'Invalid JSON payload',
    ]);
}

$event = (string) ($data['event'] ?? '');
$GLOBALS['razorpayWebhookPayload'] = $data;

razorpayWebhookLog('Webhook verified', [
    'event' => $event,
]);

if ($event === 'subscription.charged') {
    require __DIR__ . '/webhook/subscription-charged.php';
}

if ($event === 'payment.failed') {
    require __DIR__ . '/webhook/payment-failed.php';
}

if ($event === 'subscription.pending' || $event === 'subscription.halted') {
    handleSubscriptionPaymentIssue($conn, $data, $event);
}

if ($event === 'subscription.cancelled') {
    handleSubscriptionCancelled($conn, $data);
}

razorpayWebhookResponse(200, [
    'success' => true,
    'message' => 'Ignored',
    'event' => $event,
]);
