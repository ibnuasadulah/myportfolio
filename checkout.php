<?php
// =============================================
// GAMEVAULT — CHECKOUT & PAYMENT (Midtrans / ShopeePay)
// POST /api/checkout.php
// =============================================

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../core/DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit(json_encode(['error' => 'Method not allowed']));
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
  http_response_code(400);
  exit(json_encode(['error' => 'Invalid JSON body']));
}

// Validasi input
$required = ['items', 'game_user_id', 'payment_method'];
foreach ($required as $field) {
  if (empty($body[$field])) {
    http_response_code(422);
    exit(json_encode(['error' => "Field '{$field}' wajib diisi"]));
  }
}

$items         = $body['items'];          // [{product_id, qty}]
$gameUserId    = trim($body['game_user_id']);
$gameServer    = trim($body['game_server'] ?? '');
$paymentMethod = $body['payment_method']; // shopeepay | qris | bca | mandiri | bni
$userId        = $_SESSION['user_id'] ?? null;

// ---- Hitung total ----
$subtotal = 0;
$orderItems = [];
foreach ($items as $item) {
  $product = DB::fetchOne(
    'SELECT * FROM products WHERE id = ? AND is_active = 1',
    [$item['product_id']]
  );
  if (!$product) {
    http_response_code(404);
    exit(json_encode(['error' => "Produk ID {$item['product_id']} tidak ditemukan"]));
  }
  $qty = max(1, (int)($item['qty'] ?? 1));
  $subtotal += $product['price'] * $qty;
  $orderItems[] = [
    'product'     => $product,
    'qty'         => $qty,
  ];
}

// Fee (opsional, bisa 0)
$fee   = 0;
$total = $subtotal + $fee;

// ---- Buat order ----
$orderCode = 'GV-' . strtoupper(substr(uniqid(), -8)) . '-' . date('ymd');

$orderId = DB::insert('orders', [
  'user_id'        => $userId,
  'order_code'     => $orderCode,
  'status'         => 'pending',
  'subtotal'       => $subtotal,
  'fee'            => $fee,
  'total'          => $total,
  'payment_method' => $paymentMethod,
]);

foreach ($orderItems as $oi) {
  DB::insert('order_items', [
    'order_id'     => $orderId,
    'product_id'   => $oi['product']['id'],
    'product_name' => $oi['product']['name'],
    'price'        => $oi['product']['price'],
    'qty'          => $oi['qty'],
    'game_user_id' => $gameUserId,
    'game_server'  => $gameServer,
  ]);
}

// ---- Integrasi Midtrans (support ShopeePay, QRIS, VA, dll) ----
$midtransResult = createMidtransTransaction($orderId, $orderCode, $total, $paymentMethod, $body);

echo json_encode([
  'success'     => true,
  'order_code'  => $orderCode,
  'order_id'    => $orderId,
  'total'       => $total,
  'payment'     => $midtransResult,
]);

// =============================================
// FUNGSI MIDTRANS
// Docs: https://docs.midtrans.com
// =============================================
function createMidtransTransaction(int $orderId, string $orderCode, float $total, string $method, array $body): array {
  $serverKey   = MIDTRANS_SERVER_KEY;
  $isProduction = MIDTRANS_IS_PRODUCTION;
  $baseUrl     = $isProduction
    ? 'https://api.midtrans.com/v2/charge'
    : 'https://api.sandbox.midtrans.com/v2/charge';

  // Map payment method ke Midtrans payment type
  $paymentType = match($method) {
    'shopeepay'     => 'shopeepay',
    'qris'          => 'qris',
    'bca'           => 'bank_transfer',
    'mandiri'       => 'bank_transfer',
    'bni'           => 'bank_transfer',
    default         => 'qris',
  };

  $payload = [
    'payment_type'     => $paymentType,
    'transaction_details' => [
      'order_id'       => $orderCode,
      'gross_amount'   => (int) $total,
    ],
    'customer_details' => [
      'first_name'     => $body['name'] ?? 'Pelanggan',
      'email'          => $body['email'] ?? 'noemail@gamevault.id',
      'phone'          => $body['phone'] ?? '',
    ],
  ];

  // Tambahan spesifik per metode
  if ($paymentType === 'shopeepay') {
    $payload['shopeepay'] = [
      'callback_url' => APP_URL . '/payment-result.php',
    ];
  } elseif ($paymentType === 'qris') {
    $payload['qris'] = ['acquirer' => 'gopay'];
  } elseif ($paymentType === 'bank_transfer') {
    $payload['bank_transfer'] = ['bank' => $method]; // bca | mandiri | bni
  }

  $ch = curl_init($baseUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Accept: application/json',
      'Authorization: Basic ' . base64_encode($serverKey . ':'),
    ],
    CURLOPT_SSL_VERIFYPEER => $isProduction,
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $result = json_decode($response, true);

  if ($httpCode === 200 || $httpCode === 201) {
    // Simpan log payment
    DB::insert('payments', [
      'order_id'       => $orderId,
      'gateway'        => 'midtrans',
      'gateway_txn_id' => $result['transaction_id'] ?? null,
      'amount'         => $total,
      'status'         => 'pending',
      'payload'        => json_encode($result),
    ]);
    return $result;
  }

  // Jika gagal, kembalikan error
  return ['error' => $result['status_message'] ?? 'Payment gateway error'];
}
