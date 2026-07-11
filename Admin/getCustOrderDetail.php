<?php
$allowed_origins = [
    "https://online-shirt-shop-k2msvqaim-mirols-projects-b0ff7259.vercel.app",
    "https://online-shirt-shop-cyan.vercel.app",
    "http://localhost:3000" // Contoh untuk testing lokal
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
include '../db.php';
include '../tokenJana.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean(); 
    
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    $adminToken = $data['token'] ?? null;
      

   
    if (!isset($data['token'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Token is required "]);
        exit();
    };

 
 

   
    try {

     $dataToken = decode_token($adminToken);
    
   
    $adminRole = $dataToken['role'] ?? null;
    $adminId = $dataToken['id'] ?? null;
    $orderId = $data['orderId'] ?? null;

     if ($adminRole === 'user' || $adminRole === 'customer') {
        echo json_encode(["status" => "error", "message" => "Access denied: User role cannot access admin dashboard"]);
        exit;
    }

        if ($adminRole === 'admin') { 
           
         $sqlOrder =" SELECT 
                        o.id AS orderId,
                        o.order_ref AS order_ref,
                        u.username AS recipient_name,
                        u.phone AS recipient_phone,
                        o.address AS recipient_address,
                        o.status AS recipient_status,
                        o.courier AS recipient_courier,
                        o.total_amount AS total_amount,
                        o.shipping_fee AS shipping_fee,
                        (o.total_amount + o.shipping_fee) AS grand_total,
                        o.tracking_number AS tracking_number,
                        o.order_date AS order_date
                    FROM orders o
                    JOIN users u ON o.user_id = u.id
                    WHERE o.id = :orderId";

        $stmtOrder = $pdo->prepare($sqlOrder);
        $stmtOrder->bindParam(':orderId', $orderId);
        $stmtOrder->execute();
        $orderDetails = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if(!$orderDetails) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Order not found"
            ]);
            exit();
        }
        
        
        $sqlOrderItems = "SELECT 
                        oi.id AS order_item_id,
                        p.name AS product_name,
                        pv.size AS product_size,
                        pv.color AS product_color,                                         
                        oi.quantity AS product_quantity,
                        oi.price AS product_price
                    FROM order_items oi
                    JOIN product_variants pv ON oi.variant_id = pv.id
                    JOIN products p ON pv.product_id = p.id
                    WHERE oi.order_id = :orderId";

        $stmtOrderItems = $pdo->prepare($sqlOrderItems);
        $stmtOrderItems->bindParam(':orderId', $orderId);
        $stmtOrderItems->execute();
        $orderItems = $stmtOrderItems->fetchAll(PDO::FETCH_ASSOC);


        $formatedData = [
            'orderDetails' => $orderDetails,
            'orderItems' => $orderItems
        ];
        $query3 = "SELECT name FROM couriers WHERE status = 1";
        $stmt3 = $pdo->prepare($query3);
        $stmt3->execute();
        $courierList = $stmt3->fetchAll(PDO::FETCH_COLUMN);


        echo json_encode([
            "status" => "success",
            "data" => $formatedData,
            "courierList" => $courierList
        ]);


                                
        } else {
            echo json_encode(["status" => "error", "message" => "Access denied: Invalid role" ,"data" => $dataToken]);
            exit;
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getLine()
        ]);
    }
    exit;
}
?>