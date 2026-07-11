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
include 'env_loader.php';



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
    $productId = $data['productId'] ?? null;

     if ($adminRole === 'user') {
        echo json_encode(["status" => "error", "message" => "Access denied: User role cannot access admin dashboard"]);
        exit;
    }

    if($adminRole === 'admin'){
        if(empty($productId)){
            echo json_encode(["status" => "error", "message" => "Product ID is required"]);
            exit;
        }

    $sqlProduct = "SELECT
                            id AS product_id,
                            name AS product_name,
                            price AS product_price,
                            description AS product_description,
                            image_url AS product_image
                            FROM products 
                            WHERE id = :productId
                            GROUP BY id, name, price, description, image_url
                            ";

        $stmtProduct = $pdo->prepare($sqlProduct);
        $stmtProduct->bindParam(':productId', $productId, PDO::PARAM_INT);
        $stmtProduct->execute();
        $resultProduct = $stmtProduct->fetchAll(PDO::FETCH_ASSOC);



        $formatedData = [
            "status" => 'success',
            "products" => $resultProduct,

        ];

        foreach ($resultProduct as $product) {
            $productId = $product['product_id'];
            $sqlVariants = "SELECT id AS variant_id, size,color, stock FROM product_variants WHERE product_id = :productId";
            $stmtVariants = $pdo->prepare($sqlVariants);
            $stmtVariants->bindParam(':productId', $productId, PDO::PARAM_INT);
            $stmtVariants->execute();
            $variants = $stmtVariants->fetchAll(PDO::FETCH_ASSOC);

            // Tambahkan varian ke dalam produk
            $formatedData['variants'] = $variants;
           
        }

       



       

        

                     



        echo json_encode($formatedData);




    }

       

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