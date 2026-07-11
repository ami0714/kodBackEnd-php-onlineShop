<?php
// Letak ni paling atas untuk tangkap ralat dan hantar sebagai JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Pastikan 0 supaya JSON tak rosak dengan HTML

$allowed_origins = [
    "https://online-shirt-shop-k2msvqaim-mirols-projects-b0ff7259.vercel.app",
    "https://online-shirt-shop-cyan.vercel.app",
    "http://localhost:5173"  // Contoh untuk testing lokal
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
    
    // Header tambahan yang biasanya diperlukan untuk CORS
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Content-Type: application/json; charset=UTF-8");

}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// Tambah check untuk fail supaya tak crash
if (!file_exists('db.php') || !file_exists('tokenJana.php')) {
    echo json_encode(["status" => "error", "message" => "Fail sistem hilang (db.php/tokenJana.php)"]);
    exit;
}

include 'db.php';
include 'tokenJana.php';
include 'env_loader.php';

loadEnv(__DIR__ . '/.env');
$Tykey = $_ENV['TOYYIBPAY_SECRET_KEY'];
$TYcc = $_ENV['TOYYIBPAY_CATEGORY_CODE'];




$input = json_decode(file_get_contents("php://input"), true);
$token = $input['token'] ?? '';
$address = $input['address'] ?? '';

if (empty($token) || empty($address)) {
    echo json_encode(['status' => 'error', 'message' => 'Token atau alamat tidak lengkap.']);
    exit;
}

// 2. DECODE    JWT
$userToken = decode_token($token);
if (!$userToken || !isset($userToken['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak sah.']);
    exit;
}
$user_id = $userToken['id'];

try {
    $pdo->beginTransaction();

    // 3. Ambil data user
    $stmtUser = $pdo->prepare("SELECT username, email, phone FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch();
    if (!$user) throw new Exception("Pengguna tidak wujud.");

    // 4. Tarik cart (Guna query yang sama tapi pastikan table nama betul)
    $stmtCart = $pdo->prepare("
        SELECT c.variant_id, c.quantity, p.price 
        FROM cart_items c
        JOIN product_variants pv ON c.variant_id = pv.id
        JOIN products p ON pv.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmtCart->execute([$user_id]);
    $cart_items = $stmtCart->fetchAll();

    if (empty($cart_items)) throw new Exception("Troli anda kosong.");

    $total_amount = 0;
    foreach ($cart_items as $item) $total_amount += ($item['price'] * $item['quantity']);

    //get Shipping fee
    $sqlShipping = "SELECT price FROM shipping_settings WHERE id = 1";
    $stmtShipping = $pdo->prepare($sqlShipping);
    $stmtShipping->execute();
    $shipping_fee = $stmtShipping->fetchColumn();

    // 5. Insert Order
    $order_ref = "ORD-" . time() . rand(100, 999);
    $stmtOrder = $pdo->prepare("INSERT INTO orders (order_ref, user_id, address, order_date, total_amount,shipping_fee, status) VALUES (?, ?, ?, NOW(), ?, ?, 'pending')");
    $stmtOrder->execute([$order_ref, $user_id, $address, $total_amount, $shipping_fee]);
    $order_id = $pdo->lastInsertId();

    // 6. Insert Order Items
    $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, variant_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($cart_items as $item) {
        $stmtItem->execute([$order_id, $item['variant_id'], $item['quantity'], $item['price']]);
    }

    // 7. ToyyibPay
    $toyyib_data = array(
        'userSecretKey' => $Tykey,
        'categoryCode' => $TYcc,
        'billName' => 'Order ' . $order_ref,
        'billDescription' => 'Pembayaran pesanan',
        'billPriceSetting' => 1,
        'billPayorInfo' => 1,
        'billAmount' => ($total_amount + $shipping_fee) * 100,
        'billReturnUrl' => 'https://online-shirt-shop-cyan.vercel.app/Receipt',
        'billCallbackUrl' => 'https://unedited-craftily-armored.ngrok-free.dev/KopiKainApi/payment_callback.php',
        'billExternalReferenceNo' => $order_ref,
        'billTo' => $user['username'],
        'billEmail' => $user['email'],
        'billPhone' => $user['phone']
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://dev.toyyibpay.com/index.php/api/createBill');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $toyyib_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Konfigurasi Tambahan untuk atasi Connection Reset
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); // Paksa TLS 1.2
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);        // Paksa IPv4
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $res = json_decode($response);

    if (is_array($res) && isset($res[0]->BillCode)) {
        $pdo->prepare("UPDATE orders SET billcode = ? WHERE id = ?")->execute([$res[0]->BillCode, $order_id]);
        $pdo->commit();
        echo json_encode(["status" => "success", "payment_url" => "https://dev.toyyibpay.com/" . $res[0]->BillCode]);
    } else {
        throw new Exception("ToyyibPay gagal: " . ($res[0]->msg ?? 'Unknown error'));
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}