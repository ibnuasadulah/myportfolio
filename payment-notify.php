<?php
// =============================================
// GAMEVAULT — PAYMENT WEBHOOK (Midtrans Notification)
// POST /api/payment-notify.php
// Daftarkan URL ini di dashboard Midtrans:
//   https://yourdomain.com/api/payment-notify.php
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../core/DB.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
  http_response_code(400);
  exit(json_encode(['error' => 'Invalid payload']));
}

$orderCode     = $payload['order_id']         ?? '';
$transactionId = $payload['transaction_id']   ?? '';
$txStatus      = $payload['transaction_status'] ?? '';
$fraudStatus   = $payload['fraud_status']     ?? 'accept';
$grossAmount   = (float)($payload['gross_amount'] ?? 0);
$signatureKey  = $payload['signature_key']    ?? '';

// ---- Verifikasi signature Midtrans ----
$expectedSig = hash('sha512',
  $orderCode . $payload['status_code'] . $grossAmount . MIDTRANS_SERVER_KEY
);
if (!hash_equals($expectedSig, $signatureKey)) {
  http_response_code(403);
  exit(json_encode(['error' => 'Invalid signature']));
}

// ---- Cari order ----
$order = DB::fetchOne('SELECT * FROM orders WHERE order_code = ?', [$orderCode]);
if (!$order) {
  http_response_code(404);
  exit(json_encode(['error' => 'Order not found']));
}

// ---- Update status berdasarkan status Midtrans ----
$newOrderStatus    = $order['status'];
$newPaymentStatus  = 'pending';

if ($txStatus === 'capture' || $txStatus === 'settlement') {
  if ($fraudStatus === 'accept') {
    $newOrderStatus   = 'paid';
    $newPaymentStatus = 'success';
    // Tandai waktu pembayaran
    DB::update('orders', [
      'status'      => 'paid',
      'payment_ref' => $transactionId,
      'paid_at'     => date('Y-m-d H:i:s'),
    ], 'order_code = ?', [$orderCode]);

    // Proses otomatis (voucher digital dll) bisa dipanggil di sini
    processOrderFulfillment($order['id']);
  }
} elseif (in_array($txStatus, ['deny', 'cancel', 'failure'])) {
  $newOrderStatus   = 'cancelled';
  $newPaymentStatus = 'failed';
  DB::update('orders', ['status' => 'cancelled'], 'order_code = ?', [$orderCode]);
} elseif ($txStatus === 'expire') {
  $newOrderStatus   = 'cancelled';
  $newPaymentStatus = 'expired';
  DB::update('orders', ['status' => 'cancelled'], 'order_code = ?', [$orderCode]);
}

// Update log payment
DB::update('payments', [
  'status'         => $newPaymentStatus,
  'gateway_txn_id' => $transactionId,
  'payload'        => json_encode($payload),
], 'order_id = ?', [$order['id']]);

echo json_encode(['ok' => true]);

// ---- Fungsi fulfillment ----
function processOrderFulfillment(int $orderId): void {
  // Tandai sebagai processing/completed
  DB::update('orders', ['status' => 'processing'], 'id = ?', [$orderId]);

  $items = DB::fetchAll(
    'SELECT oi.*, p.meta FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?',
    [$orderId]
  );

  foreach ($items as $item) {
    // Di sini bisa integrasi dengan API top-up otomatis
    // atau kirim voucher ke email pembeli
    // Contoh: markDelivered($item['id'], 'VOUCHER-CODE-XXX');
  }

  DB::update('orders', ['status' => 'completed'], 'id = ?', [$orderId]);
}
