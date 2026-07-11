<?php
$allowed_origins = [
    "https://online-shirt-shop-k2msvqaim-mirols-projects-b0ff7259.vercel.app",
    "https://online-shirt-shop-cyan.vercel.app",
    "http://localhost:5173" // Contoh untuk testing lokal
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
    
    if (empty($token)) {
        echo json_encode(["status" => "error", "message" => "Token is required"]);
        exit;
    }

    try {
       
            $data_user = decode_token($token);

            $userId = $data_user['id'];
       
        $stmt = $pdo->prepare("SELECT username, email,phone, role FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode([
                "status" => "success",
                "username" => $user['username'],
                "email" => $user['email'],
                "phone" => $user['phone'],
                "role" => $user['role'] // Mengandungi username, email, dan role 
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "User tidak wujud"]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
    exit;
}
?>