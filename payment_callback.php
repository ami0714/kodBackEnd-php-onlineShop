<?php
require_once 'db.php';
header('Content-Type: text/plain; charset=UTF-8');

// 1. Terima data (ToyyibPay hantar POST)
$billcode = $_POST['billcode'] ?? '';
$status_id = $_POST['status_id'] ?? 0;
// Note: Kadang-kadang nama field ToyyibPay boleh berbeza, pastikan reference_no betul
$order_ref = $_POST['order_ref'] ?? ''; 

// 2. Log untuk Debugging
error_log("Callback Diterima: " . json_encode($_POST));

if (empty($billcode)) {
    echo "ERROR_INVALID_BILLCODE";
    exit();
}

try {
    // 3. Semak status terus dari database
    // Pastikan nama table betul (Orders atau orders? SQL biasanya case-insensitive tapi elok konsisten)
    $stmt = $pdo->prepare("SELECT id, status, user_id FROM orders WHERE billcode = :billcode");
    $stmt->execute([':billcode' => $billcode]);
    $order = $stmt->fetch();

    if (!$order) {
        error_log("Order tidak dijumpai: " . $billcode);
        exit("ERROR_NOT_FOUND");
    }

    if ($order['status'] === 'paid') {
        echo "OK"; // Sudah paid, jangan proses lagi
        exit();
    }

    // 4. Verifikasi cURL ke ToyyibPay
    $toyyib_verify_data = [
        'billCode' => $billcode,
        'billpaymentStatus' => 1 
    ];

    $ch = curl_init('https://dev.toyyibpay.com/index.php/api/getBillTransactions');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $toyyib_verify_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); // Paksa TLS 1.2
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); 
    // Tambah timeout supaya tak 'loading' selamanya
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 

    $response = curl_exec($ch);
    curl_close($ch);

    $transactions = json_decode($response, true);

    // 5. Semakan Status (Guna array ['billpaymentStatus'])
    if (!empty($transactions) && isset($transactions[0]['billpaymentStatus'])) {
        $real_status = $transactions[0]['billpaymentStatus'];

        if ($real_status == '1') {

            $stmtOrder = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = :Id");
            $stmtOrder->execute([':Id' => $order['id']]);
            $order_items = $stmtOrder->fetchAll();

            $stmtUpdate = $pdo->prepare("UPDATE product_variants SET stock = stock - :quantity WHERE id = :variantId");
            foreach ($order_items as $item) {
                $stmtUpdate->execute([
                    ':quantity' => $item['quantity'],
                    ':variantId' => $item['variant_id']
                ]);
            }

            // PERBAIKAN: Gunakan nama table yang betul (Contoh: orders)
            $update = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE billcode = :billcode");
            $update->execute([':billcode' => $billcode]);

            $delete = $pdo->prepare("DELETE FROM cart_items WHERE user_id = :user_id");
            $delete->execute([':user_id' => $order['user_id']]);
            
            echo "OK"; 
            exit();
        }
    }

    echo "ERROR_INVALID_STATUS";
    exit();

} catch (Exception $e) {
    error_log("Callback Error: " . $e->getMessage());
    echo "ERROR_SYSTEM";
    exit();
}