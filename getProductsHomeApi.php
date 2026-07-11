<?php
// Letak header CORS paling atas untuk elakkan sekatan browser

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // SQL query untuk ambil semua produk (Boleh letak ORDER BY id DESC kalau nak produk terbaru dulu)
        $stmt = $pdo->prepare("SELECT id, name, price, description, image_url FROM products ORDER BY id DESC");
        $stmt->execute();
        $products = $stmt->fetchAll();

        // Pulangkan format JSON yang ditunggu oleh apiRequest() di React tadi
        echo json_encode([
            "status" => "success",
            "data" => $products
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