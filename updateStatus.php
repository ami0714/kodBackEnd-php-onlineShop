<?php
// Tukar * kepada origin port Vite awak supaya credentials: 'include' berfungsi

$allowed_origins = [
    "https://online-shirt-shop-k2msvqaim-mirols-projects-b0ff7259.vercel.app",
    "https://kedai-baju-lain.vercel.app",
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






include 'db.php';
include 'tokenJana.php';

// Lepaskan request OPTIONS (CORS preflight) yang dihantar oleh browser secara automatik
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   // Bersihkan sebarang whitespace luar
   ob_clean();
    
    // 1. Ambil input JSON raw dulu
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    if (empty($data)) {
        echo json_encode(["status"=> "error", "message" => "data kosong"]);
        
    }
    
    $token  = $data['token'] ?? null;
    $orderId = $data['orderId'] ?? null;
   
    
    // Semakan token dan wajib ada
    if (empty($token) ) {
        echo json_encode(["status" => "error", "message" => "Aksi atau token tidak lengkap."]);
        exit;
    }
    

    try {
     $data_user = decode_token($token); 
    $userId    = $data_user['id'];
        $status =  "shipped";
        $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered',updated_at = NOW()  WHERE user_id = :userId AND id = :id AND status = :status ");
        $stmt->bindParam(':userId', $userId);
         $stmt->bindParam(':id', $orderId);
         $stmt->bindParam(':status', $status);

        

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "berjaya update"]);
        } else {
            echo json_encode(["status" => "error", "message" => "gagal update"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getLine()]);
    }
    exit;
}
?>