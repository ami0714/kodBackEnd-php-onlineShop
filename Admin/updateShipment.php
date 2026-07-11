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
    $selectedCourier = $data['courier'] ?? null;
    $trackingNumber = $data['trackingNumber'] ?? null;
    $orderId = $data['orderId'] ?? null;
     if ($adminRole === 'user' || $adminRole === 'customer') {
        echo json_encode(["status" => "error", "message" => "Access denied: User role cannot access admin dashboard"]);
        exit;
    }

        if ($adminRole === 'admin') { 
           
            $sqlUpdate = "UPDATE orders SET courier = :courier, tracking_number = :trackingNumber,status = 'shipped' WHERE id = :orderId";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':courier', $selectedCourier);
            $stmtUpdate->bindParam(':trackingNumber', $trackingNumber);
            $stmtUpdate->bindParam(':orderId', $orderId);
            
                if($stmtUpdate->execute()) {
                    echo json_encode([
                        "status" => "success",
                        "message" => "Shipment details updated successfully"
                    ]);
                } else {
                    echo json_encode([
                        "status" => "error",
                        "message" => "Failed to update shipment details"
                    ]);
                };
           
         
            
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