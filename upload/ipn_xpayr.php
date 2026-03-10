<?php
/**
 * XPayr webhook callback for Zen Cart.
 *
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @copyright Copyright (c) 2024 XPayr - https://xpayr.com
 */

require('includes/application_top.php');

header('Content-Type: application/json; charset=utf-8');

function xpayr_cfg($key, $default = '')
{
    global $db;

    if (defined($key)) {
        return constant($key);
    }

    $query = $db->Execute(
        "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . zen_db_input($key) . "' LIMIT 1"
    );

    if ($query->EOF) {
        return $default;
    }

    return (string) $query->fields['configuration_value'];
}

function xpayr_transaction_table()
{
    return DB_PREFIX . 'xpayr_transactions';
}

function xpayr_log($message, $force = false)
{
    $debug = xpayr_cfg('MODULE_PAYMENT_XPAYR_DEBUG', 'False') === 'True';
    if (!$force && !$debug) {
        return;
    }

    $line = '[' . date('c') . '] ' . (string) $message . "\n";
    $logPath = DIR_FS_LOGS . '/xpayr-zencart.log';
    @file_put_contents($logPath, $line, FILE_APPEND);
}

function xpayr_json_response($code, array $payload)
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || trim($raw) === '') {
    xpayr_json_response(400, ['ok' => false, 'error' => 'empty payload']);
}

$secret = trim((string) xpayr_cfg('MODULE_PAYMENT_XPAYR_WEBHOOK_SECRET', ''));
if ($secret !== '') {
    $headerSig = isset($_SERVER['HTTP_X_XPAYR_SIGNATURE']) ? trim((string) $_SERVER['HTTP_X_XPAYR_SIGNATURE']) : '';
    if (strpos($headerSig, 'sha256=') === 0) {
        $headerSig = substr($headerSig, 7);
    }

    $expected = hash_hmac('sha256', $raw, $secret);
    if ($headerSig === '' || !hash_equals($expected, $headerSig)) {
        xpayr_log('Webhook signature mismatch', true);
        xpayr_json_response(401, ['ok' => false, 'error' => 'invalid signature']);
    }
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    xpayr_json_response(400, ['ok' => false, 'error' => 'invalid json']);
}

$eventType = strtolower((string) ($payload['type'] ?? $payload['event'] ?? ''));
$data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];

$sessionId = trim((string) ($data['session_id'] ?? $data['id'] ?? ''));
$invoiceId = trim((string) ($data['invoice_id'] ?? ''));
$status = strtolower((string) ($data['status'] ?? ''));
$meta = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
$orderReference = trim((string) ($meta['order_id'] ?? $meta['order_reference'] ?? ''));
$orderId = isset($meta['zencart_order_id']) ? (int) $meta['zencart_order_id'] : 0;

if ($eventType === '' && $status !== '') {
    $eventType = 'payment.' . $status;
}

$table = xpayr_transaction_table();
$row = null;

if ($sessionId !== '') {
    $q = $db->Execute("SELECT * FROM " . $table . " WHERE session_id = '" . zen_db_input($sessionId) . "' LIMIT 1");
    if (!$q->EOF) {
        $row = $q->fields;
    }
}

if (!$row && $invoiceId !== '') {
    $q = $db->Execute("SELECT * FROM " . $table . " WHERE invoice_id = '" . zen_db_input($invoiceId) . "' LIMIT 1");
    if (!$q->EOF) {
        $row = $q->fields;
    }
}

if (!$row && $orderReference !== '') {
    $q = $db->Execute("SELECT * FROM " . $table . " WHERE order_reference = '" . zen_db_input($orderReference) . "' LIMIT 1");
    if (!$q->EOF) {
        $row = $q->fields;
    }
}

if ($row && !empty($row['order_id'])) {
    $orderId = (int) $row['order_id'];
}

if ($orderId <= 0 && preg_match('/^ZC-(\d+)$/', $orderReference, $m)) {
    $orderId = (int) $m[1];
}

if ($orderId <= 0) {
    xpayr_log('Order not found for webhook payload: ' . $raw, true);
    xpayr_json_response(404, ['ok' => false, 'error' => 'order not found']);
}

$paidStatus = (int) xpayr_cfg('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_PAID', '2');
$failedStatus = (int) xpayr_cfg('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED', '1');
$defaultStatus = (int) xpayr_cfg('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID', '1');

$newStatus = $defaultStatus;
$historyComment = 'XPayr webhook received';
$txStatus = $status !== '' ? $status : 'pending';

if (in_array($eventType, ['payment.completed', 'payment.confirmed'], true) || $status === 'completed' || $status === 'confirmed') {
    $newStatus = $paidStatus;
    $historyComment = 'XPayr payment completed';
    $txStatus = 'completed';
} elseif (in_array($eventType, ['payment.failed'], true) || $status === 'failed') {
    $newStatus = $failedStatus;
    $historyComment = 'XPayr payment failed';
    $txStatus = 'failed';
} elseif (in_array($eventType, ['payment.expired'], true) || $status === 'expired') {
    $newStatus = $failedStatus;
    $historyComment = 'XPayr payment expired';
    $txStatus = 'expired';
} elseif (in_array($eventType, ['payment.pending', 'payment.processing'], true) || in_array($status, ['pending', 'processing'], true)) {
    $newStatus = $defaultStatus;
    $historyComment = 'XPayr payment pending';
    $txStatus = 'pending';
}

$db->Execute(
    "UPDATE " . TABLE_ORDERS . "
     SET orders_status = '" . (int) $newStatus . "', last_modified = now()
     WHERE orders_id = '" . (int) $orderId . "'"
);

$comment = $historyComment;
if ($sessionId !== '') {
    $comment .= ' | session: ' . $sessionId;
}
if ($invoiceId !== '') {
    $comment .= ' | invoice: ' . $invoiceId;
}

$db->Execute(
    "INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . "
    (orders_id, orders_status_id, date_added, customer_notified, comments)
    VALUES
    ('" . (int) $orderId . "', '" . (int) $newStatus . "', now(), 0, '" . zen_db_input($comment) . "')"
);

$safeSession = zen_db_input(substr((string) $sessionId, 0, 80));
$safeInvoice = zen_db_input(substr((string) $invoiceId, 0, 32));
$safeReference = zen_db_input(substr((string) $orderReference, 0, 64));
$safeStatus = zen_db_input(substr((string) $txStatus, 0, 32));
$safePayload = zen_db_input($raw);

$exists = $db->Execute("SELECT id FROM " . $table . " WHERE order_id = '" . (int) $orderId . "' LIMIT 1");
if ($exists->EOF) {
    $db->Execute(
        "INSERT INTO " . $table . "
        (order_id, order_reference, session_id, invoice_id, payment_url, status, payload_json, created_at, updated_at)
        VALUES
        ('" . (int) $orderId . "', '" . $safeReference . "', '" . $safeSession . "', '" . $safeInvoice . "', '', '" . $safeStatus . "', '" . $safePayload . "', now(), now())"
    );
} else {
    $db->Execute(
        "UPDATE " . $table . "
         SET session_id = IF('" . $safeSession . "'='', session_id, '" . $safeSession . "'),
             invoice_id = IF('" . $safeInvoice . "'='', invoice_id, '" . $safeInvoice . "'),
             order_reference = IF('" . $safeReference . "'='', order_reference, '" . $safeReference . "'),
             status = '" . $safeStatus . "',
             payload_json = '" . $safePayload . "',
             updated_at = now()
         WHERE order_id = '" . (int) $orderId . "'"
    );
}

xpayr_json_response(200, ['ok' => true]);
