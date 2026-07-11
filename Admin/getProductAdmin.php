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

     if ($adminRole === 'user') {
        echo json_encode(["status" => "error", "message" => "Access denied: User role cannot access admin dashboard"]);
        exit;
    }

    if($adminRole === 'admin'){

    $sqlProduct = "SELECT
                        p.id AS product_id,
                            p.name AS product_name,
                            p.price AS product_price,
                            p.description AS product_description,
                            p.image_url AS product_image,
                            SUM(v.stock) AS total_stock,
                            if(SUM(v.stock) > 0, 'In Stock', 'Out of Stock') AS stock_status,
                            SUM(oi.price) AS total_sales

                        FROM products p
                        LEFT JOIN product_variants v ON p.id = v.product_id
                        LEFT JOIN order_items oi ON v.id = oi.variant_id
                        GROUP BY p.id, p.name, p.price, p.description, p.image_url";

        $stmtProduct = $pdo->prepare($sqlProduct);
        $stmtProduct->execute();
        $resultProduct = $stmtProduct->fetchAll(PDO::FETCH_ASSOC);

       



       

        

                     



        echo json_encode([
            "status" => "success",
            "products" => $resultProduct
        ]);




    }

       

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getLine() . $e->getMessage()
        ]);
    }
    exit;
}
?>