<?php
// Tukar * kepada origin port Vite awak supaya credentials: 'include' berfungsi

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




include 'db.php';

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
    
    // 2. Ekstrak data dari hasil decode JSON tadi
    $_username = $data['username'] ?? null;
    $_email = $data['email'] ?? null;
    $_phone = $data['phone'] ?? null;
    $_password = $data['password'] ?? null;
    
    // Semak kalau data kosong
    if (empty($_username) || empty($_password)) {
        echo json_encode(["status" => "error", "message" => "Username and password are required"]);
        exit;
    }

    $_hashed_password = password_hash($_password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email,phone, password_hash) VALUES (:username,:email,:phone, :password)");
        $stmt->bindParam(':username', $_username);
        $stmt->bindParam(':email', $_email);
         $stmt->bindParam(':phone', $_phone);
        $stmt->bindParam(':password', $_hashed_password);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User registered successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to register user"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
    exit;
}
?>