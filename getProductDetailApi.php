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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean(); // Bersihkan buffer atas
    
    // 1. Tangkap input JSON dari React
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    $idProduct = $data['idProduct'] ?? null;
    
    if (empty($idProduct)) {
        echo json_encode(["status" => "error", "message" => "Product ID is required"]);
        exit;
    }

    try {
        // Query A: Ambil maklumat utama produk
        $stmtProduct = $pdo->prepare("SELECT id, name, price, description, image_url FROM products WHERE id = :id");
        $stmtProduct->bindParam(':id', $idProduct, PDO::PARAM_INT);
        $stmtProduct->execute();
        $productInfo = $stmtProduct->fetch();

        if (!$productInfo) {
            echo json_encode(["status" => "error", "message" => "Product not found"]);
            exit;
        }

        // Query B: Ambil saiz yang unik (DISTINCT) untuk produk ini dari table variants
        $stmtSize = $pdo->prepare("SELECT DISTINCT size FROM product_variants WHERE product_id = :id AND stock > 0");
        $stmtSize->bindParam(':id', $idProduct, PDO::PARAM_INT);
        $stmtSize->execute();
        // Tukar format array objek [ {"size": "M"}, {"size": "L"} ] menjadi array flat [ "M", "L" ]
        $sizeList = $stmtSize->fetchAll(PDO::FETCH_COLUMN);

        // Query C: Ambil warna yang unik (DISTINCT) untuk produk ini dari table variants
        $stmtColor = $pdo->prepare("SELECT DISTINCT color FROM product_variants WHERE product_id = :id AND stock > 0");
        $stmtColor->bindParam(':id', $idProduct, PDO::PARAM_INT);
        $stmtColor->execute();
        $colorList = $stmtColor->fetchAll(PDO::FETCH_COLUMN);//jadikan dari array objek kepada array satu dimensi yang lebih ringkas

        // Pulangkan semua hasil  ke React
        echo json_encode([
            "status" => "success",
            "productInfo" => $productInfo,
            "sizeList" => $sizeList,
            "colorList" => $colorList
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