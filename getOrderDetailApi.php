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
    ob_clean(); // Bersihkan buffer output

    // 1. Tangkap input JSON dari React
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    // Semak token wujud
    if (!isset($data['token'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Token is required"]);
        exit();
    }

    $token = $data['token'];

    try {
        // Decode token untuk dapatkan userId
        $data_user = decode_token($token);
        $userId = $data_user['id'];

        // Guna o.id AS order_ref kerana tiada kolum order_ref dalam jadual asal
        $sql = "SELECT
                    o.id AS id,
                    o.order_ref AS order_ref,
                    o.order_date AS order_date,
                    o.total_amount AS total_amount,
                    o.status AS status,
                    o.tracking_number AS tracking_number,
                    o.courier AS courier,
                    p.name AS name,
                    pv.size AS size,
                    pv.color AS color,
                    oi.quantity AS quantity
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN product_variants pv ON oi.variant_id = pv.id
                JOIN products p ON pv.product_id = p.id
                WHERE o.user_id = :idUser
                ORDER BY o.id DESC"; // Ditambah susunan order terbaru di atas

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':idUser', $userId);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Kumpulkan data mengikut order_ref (Grouping)
        $orders = [];
        foreach ($rows as $row) {
            $orderId = $row['id'];
            
            // Jika order ini belum ada dalam array, bina struktur asasnya
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'orderRef' => $row['order_ref'],
                    'orderId' => $row['id'],
                    'orderDate' => $row['order_date'],
                    'totalAmount' => $row['total_amount'], // PEMBETULAN: Ejaan dibetulkan dari amaunt -> totalAmount
                    'status' => $row['status'],
                    'trackingNumber' => $row['tracking_number'], // DISERAGAMKAN: Jadi camelCase
                    'courier' => $row['courier'],
                    'items' => []
                ];
            }

            // Tambah item ke dalam array items order tersebut
            $orders[$orderId]['items'][] = [
                'name' => $row['name'],
                'size' => $row['size'],
                'color' => $row['color'],
                'quantity' => $row['quantity']
            ];
        }

        // 3. Tukar key array (yang berasaskan ID) kepada indeks berurutan 0,1,2...
        $formatedData = array_values($orders);

        // 4. Pulangkan hasil ke React
        echo json_encode([
            "status" => "success",
            "data" => $formatedData
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Token error: " . $e->getMessage()
        ]);
    }
    exit();
}
?>