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

include 'db.php';
include 'tokenJana.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean();
    
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    $token  = $data['token'] ?? null;
    $billcode = $data['billcode'] ?? null;
    
    
    
    // Semakan token dan wajib ada
    if (empty($token) || empty($billcode) ) {
        echo json_encode(["status" => "error", "message" => "Token, billcode, atau orderId tidak lengkap."]);
        exit;
    }

    try {
        //token user 
        $data_user = decode_token($token); 
        $userId    = $data_user['id'];
             

        $sql = "DELETE FROM orders WHERE user_id = :userId AND billcode = :billcode";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':billcode', $billcode, PDO::PARAM_STR);
       

        if( $stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => "Pesanan berjaya dibatalkan!"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Gagal membatalkan pesanan. Sila cuba lagi."
            ]);
        }

       
        
      




    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
        exit;
    }
}
?>