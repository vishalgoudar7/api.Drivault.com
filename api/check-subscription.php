<?php
declare(strict_types=1);

require '../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

function subscriptionJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function quotaToGb(mixed $quota): float
{
    $quota = strtoupper(trim((string) $quota));

    if ($quota === '') {
        return 0.0;
    }

    if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(TB|GB)?/', $quota, $matches) !== 1) {
        return 0.0;
    }

    $value = (float) $matches[1];
    $unit = $matches[2] ?? 'GB';

    return $unit === 'TB' ? $value * 1024 : $value;
}

function formatPlanLabel(array $plan): string
{
    $quota = trim((string) ($plan['quota'] ?? ''));

    return $quota !== '' ? preg_replace('/([0-9])([A-Za-z])/', '$1 $2', $quota) : (string) ($plan['name'] ?? '');
}

function formatDateLabel(?string $date): ?string
{
    if (!$date) {
        return null;
    }

    try {
        return (new DateTime($date))->format('d M Y');
    } catch (Throwable) {
        return $date;
    }
}

$username = trim((string) ($_POST['username'] ?? $_GET['username'] ?? ''));
$selectedPlanId = (int) ($_POST['selected_plan_id'] ?? $_GET['selected_plan_id'] ?? 0);
$billingCycle = strtolower(trim((string) ($_POST['billing_cycle'] ?? $_GET['billing_cycle'] ?? 'monthly')));

if ($username === '' || $selectedPlanId <= 0) {
    subscriptionJsonResponse(422, [
        'success' => false,
        'message' => 'Username and selected plan are required.',
    ]);
}

if (!preg_match('/^[a-zA-Z0-9._@+\-]{2,100}$/', $username)) {
    subscriptionJsonResponse(422, [
        'success' => false,
        'message' => 'Please enter a valid username.',
    ]);
}

if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
    subscriptionJsonResponse(422, [
        'success' => false,
        'message' => 'Invalid billing cycle.',
    ]);
}

$stmt = $conn->prepare('SELECT * FROM plans WHERE id = ? AND status = 1 LIMIT 1');
$stmt->bind_param('i', $selectedPlanId);
$stmt->execute();
$selectedPlan = $stmt->get_result()->fetch_assoc();

if (!$selectedPlan) {
    subscriptionJsonResponse(404, [
        'success' => false,
        'message' => 'Selected plan not found.',
    ]);
}

$stmt = $conn->prepare("
    SELECT s.*, p.quota AS current_plan_quota, p.name AS current_plan_name
    FROM subscriptions s
    LEFT JOIN plans p ON p.id = s.plan_id
    WHERE s.payment_status = 'Success'
      AND s.status = 'active'
      AND (
          s.user_id = ?
          OR s.drivault_email = ?
          OR s.drivault_phone = ?
          OR s.drivault_display_name = ?
      )
    ORDER BY COALESCE(s.start_date, s.created_at) DESC, s.id DESC
    LIMIT 1
");
$stmt->bind_param('ssss', $username, $username, $username, $username);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();

$selectedPlanLabel = formatPlanLabel($selectedPlan);

if (!$subscription) {
    subscriptionJsonResponse(200, [
        'success' => true,
        'action' => 'purchase',
        'username' => $username,
        'current_plan' => null,
        'selected_plan' => $selectedPlanLabel,
        'expiry_date' => null,
        'expiry_date_label' => null,
        'days_left' => null,
        'subscription_id' => null,
        'downgrade_supported' => false,
    ]);
}

$currentQuota = $subscription['current_plan_quota'] ?? $subscription['storage_quota'] ?? '';
$currentPlanLabel = formatPlanLabel([
    'quota' => $currentQuota,
    'name' => $subscription['current_plan_name'] ?? $subscription['plan_name'] ?? '',
]);

$currentPlanGb = quotaToGb($currentQuota);
$selectedPlanGb = quotaToGb($selectedPlan['quota'] ?? '');

if ($selectedPlanGb === $currentPlanGb) {
    $action = 'renew';
} elseif ($selectedPlanGb > $currentPlanGb) {
    $action = 'upgrade';
} else {
    $action = 'downgrade';
}

$expiryDate = $subscription['expiry_date'] ?? null;
$daysLeft = null;

if ($expiryDate) {
    try {
        $today = new DateTime('today');
        $expiry = new DateTime($expiryDate);
        $daysLeft = (int) $today->diff($expiry)->format('%r%a');
    } catch (Throwable) {
        $daysLeft = null;
    }
}

subscriptionJsonResponse(200, [
    'success' => true,
    'action' => $action,
    'username' => $username,
    'current_plan' => $currentPlanLabel,
    'selected_plan' => $selectedPlanLabel,
    'expiry_date' => $expiryDate,
    'expiry_date_label' => formatDateLabel($expiryDate),
    'days_left' => $daysLeft,
    'subscription_id' => (int) $subscription['id'],
    'downgrade_supported' => false,
]);
