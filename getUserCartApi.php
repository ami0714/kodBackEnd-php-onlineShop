<?php
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

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';
include 'tokenJana.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean();
    
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    $token = $data['token'] ?? null;
    
    if (empty($token) || $token === "undefined") {
        echo json_encode(["status" => "error", "message" => "Token diperlukan"]);
        exit;
    }

    try {
        $data_user = decode_token($token);
        $userId = $data_user['id']; // Anggapan fasa dummy: token adalah userId

        // SQL: Gabungkan 3 table mengikut hubungan key masing-masing
        $query = "
            SELECT 
                c.id AS cart_id,
                c.quantity,
                v.id AS variant_id,
                v.size,
                v.color,
                v.stock,
                p.name AS product_name,
                p.price AS product_price,
                p.image_url AS image
            FROM cart_items c
            INNER JOIN product_variants v ON c.variant_id = v.id
            INNER JOIN products p ON v.product_id = p.id
            WHERE c.user_id = :user_id
        ";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);


       //get Shipping fee
    $sqlShipping = "SELECT price FROM shipping_settings WHERE id = 1";
    $stmtShipping = $pdo->prepare($sqlShipping);
    $stmtShipping->execute();
    $shipping_fee = $stmtShipping->fetchColumn();

        echo json_encode([
            "status" => "success",
            "cartData" => $cartItems,
            "cartLength" => count($cartItems), // Guna count() PHP untuk kira berapa jenis barang dlm cart
            "shippingFee" => $shipping_fee
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
    }
    exit;
}
?>