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
    
    $token  = $data['token'] ?? null;
    $action = $data['action'] ?? null;
    
    // Semakan token dan wajib ada
    if (empty($token) || empty($action)) {
        echo json_encode(["status" => "error", "message" => "Aksi atau token tidak lengkap."]);
        exit;
    }

    try {
        // Dekod token user 
        $data_user = decode_token($token); 
        $userId    = $data_user['id'];

        // ----------------------------------------------------
        //  A: PADAM BARANG DARI TROLI
        // ----------------------------------------------------
        if ($action === 'delete') {
            $cartId = $data['cart_id'] ?? null;

            if (empty($cartId)) {
                echo json_encode(["status" => "error", "message" => "ID bakul diperlukan untuk pemadaman."]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = :cart_id AND user_id = :user_id");
            $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Barang berjaya dibuang dari troli!"
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Barang tidak dijumpai atau anda tidak mempunyai akses."
                ]);
            }
            exit; 
        } 
        
        // ----------------------------------------------------
        //  B: TAMBAH BARANG KE TROLI
        // ----------------------------------------------------
        else if ($action === 'add') {
            $productId = $data['productId'] ?? null;
            $size      = $data['size'] ?? null;
            $color     = $data['color'] ?? null;
            $quantity  = 1;

            if (empty($productId) || empty($size) || empty($color)) {
                echo json_encode(["status" => "error", "message" => "Sila pilih saiz, warna, dan produk dengan lengkap."]);
                exit;
            }

            // 1. Ambil variant id berdasarkan warna dan saiz
            $stmt = $pdo->prepare("SELECT id FROM product_variants WHERE color = :color AND size = :size AND product_id = :productId");
            $stmt->bindParam(':color', $color, PDO::PARAM_STR);
            $stmt->bindParam(':size', $size, PDO::PARAM_STR);
            $stmt->bindParam(':productId', $productId, PDO::PARAM_INT);
            $stmt->execute();
            $dataPv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dataPv) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Kombinasi saiz dan warna ini tidak wujud!"
                ]);
                exit;
            }

            $pv_id = $dataPv['id'];

            // 2. Sediakan arahan INSERT
            $stmtInsert = $pdo->prepare("INSERT INTO cart_items (user_id, variant_id, quantity) VALUES (:user_id, :variant_id, :quantity)");
            $stmtInsert->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtInsert->bindParam(':variant_id', $pv_id, PDO::PARAM_INT);
            $stmtInsert->bindParam(':quantity', $quantity, PDO::PARAM_INT);

            if ($stmtInsert->execute()) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Barang berjaya ditambah ke troli!"
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Barang gagal ditambah."
                ]);
            }
            exit;
        } 


        else if ($action === 'update') {
            $cartId = $data['cart_id'] ?? null;
            $newQuantity = $data['newQuantity'] ?? null;
            

            if (empty($cartId) || empty($newQuantity)) {
                echo json_encode(["status" => "error", "message" => "sila isi kuantiti"]);
                exit;
            }

            // 1. Ambil variant id berdasarkan warna dan saiz
            $stmt = $pdo->prepare("UPDATE cart_items SET quantity = :quantity WHERE id = :cartid AND user_id = :userId");
            $stmt->bindParam(':cartid', $cartId, PDO::PARAM_INT);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);
           
            

            if ($stmt->execute()) {
                echo json_encode([
                    "status" => "success",
                    "message" => "kuantiti berjaya ditambah ke troli!"
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Barang gagal ditambah."
                ]);
            }
            exit;
        } 
        
        // ----------------------------------------------------
        // JIKA ACTION LAIN YANG DIHANTAR
        // ----------------------------------------------------
        else {
            echo json_encode(["status" => "error", "message" => "Aksi tidak dikenali."]);
            exit;
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