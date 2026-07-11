<?php
error_reporting(0); 
ini_set('display_errors', 0);

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
include_once __DIR__ . "/helper/compressImage.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Ambil input data teks & JSON varian
    $token = $_POST['token'] ?? null;
    $productId = $_POST['productId'] ?? null;
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? '';
    $description = $_POST['description'] ?? '';
    $action = $_POST['action'] ?? '';
    
    // Decode data varian array dari React FormData
    $variants = isset($_POST['variants']) ? json_decode($_POST['variants'], true) : [];

    if (!$token) {
        echo json_encode(["status" => "error", "message" => "Token tidak disediakan."]);
        exit();
    }

    // Dekod token untuk semak akses admin
    $adminToken = decode_token($token);
    $admin = $adminToken['role'] ?? '';

    if ($admin !== 'admin') {
        echo json_encode(["status" => "error", "message" => "Akses ditolak. Anda bukan admin."]);
        exit();
    }

    try {
        // Mulakan SQL Transaction
        $pdo->beginTransaction();

        $imageUrlInDB = null;

        // 1. PROSES GAMBAR (Jika ada fail yang dimuat naik)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];

            // Jadikan nama fail unik
            $cleanFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9\.]/", "_", $fileName);
            $uploadDir = '../uploads/'; 

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $dest_path = $uploadDir . $cleanFileName;

            if (compressAndResizeImage($fileTmpPath, $dest_path, 800, 75)) {   
                $imageUrlInDB = 'uploads/' . $cleanFileName;
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal memindahkan fail gambar ke folder server."]);
                exit;
            }
        }

        // ==========================================
        // LOGIK PERINGKAT ACTION: EDIT
        // ==========================================
        if ($action == 'edit') {
            if (!$productId) {
                echo json_encode(["status" => "error", "message" => "ID Produk diperlukan untuk edit."]);
                exit();
            }

            // Kemaskini produk utama
            if ($imageUrlInDB) {
                $sqlUpdate = 'UPDATE products 
                              SET name = :name, description = :desc, price = :price, image_url = :imgUrl 
                              WHERE id = :id';
            } else {
                $sqlUpdate = 'UPDATE products 
                              SET name = :name, description = :desc, price = :price 
                              WHERE id = :id';
            }

            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':name', $name);
            $stmtUpdate->bindParam(':desc', $description);
            $stmtUpdate->bindParam(':price', $price);
            if ($imageUrlInDB) {
                $stmtUpdate->bindParam(':imgUrl', $imageUrlInDB);
            }
            $stmtUpdate->bindParam(':id', $productId);
            $stmtUpdate->execute();

            // --- LOGIK PENGURUSAN INTEGRITI VARIAN  ---
            // 1. Kumpul semua ID varian sedia ada yang dihantar dari React (yang tidak dibuang oleh admin)
            $keptVariantIds = [];
            if (!empty($variants) && is_array($variants)) { //jika variant tak ksong dan variant adalah array
                foreach ($variants as $variant) {
                    $vId = $variant['id'] ?? null;
                    if (!empty($vId)) {
                        $keptVariantIds[] = intval($vId); //memasukkan id dalam array jika array variant memang ada dan id ade
                    }
                }
            }

            // 2. Padam mana-mana varian di DB yang TIADA dalam senarai dikekalkan ($keptVariantIds)
            if (!empty($keptVariantIds)) { //delete semua kecuali id tidak sama dengan id dalam $keptVariant
                $sqlDeleteRemoved = 'DELETE FROM product_variants WHERE product_id = :product_id AND id NOT IN (' . implode(',', $keptVariantIds) . ')';
                $stmtDeleteRemoved = $pdo->prepare($sqlDeleteRemoved);
                $stmtDeleteRemoved->bindParam(':product_id', $productId);
                $stmtDeleteRemoved->execute();
            } else {
                // Jika langsung tiada varian dihantar, maksudnya semua varian produk ini telah dipadam di frontend
                $sqlDeleteAllVariants = 'DELETE FROM product_variants WHERE product_id = :product_id';
                $stmtDeleteAll = $pdo->prepare($sqlDeleteAllVariants);
                $stmtDeleteAll->bindParam(':product_id', $productId);
                $stmtDeleteAll->execute();
            }

            // 3. Proses Bersilang (Upsert): INSERT baru atau UPDATE yang lama
            if (!empty($variants) && is_array($variants)) {
                foreach ($variants as $variant) {
                    $vId = $variant['id'] ?? null; // Diperbaiki: guna $variant bukan $variants
                    $vSize = $variant['size'] ?? '';
                    $vColor = $variant['color'] ?? '';
                    $vStock = !empty($variant['stock']) ? intval($variant['stock']) : 0;

                    if (empty($vId)) {
                        // LOGIK INSERT (Varian Baharu)
                        $sqlVariantQuery = 'INSERT INTO product_variants (product_id, size, color, stock)
                                            VALUES (:product_id, :size, :color, :stock)';
                    } else {
                        // LOGIK UPDATE (Varian Sedia Ada)
                        $sqlVariantQuery = 'UPDATE product_variants SET 
                                            size = :size, 
                                            color = :color, 
                                            stock = :stock 
                                            WHERE id = :id 
                                            AND product_id = :product_id';
                    }

                    $stmtVariants = $pdo->prepare($sqlVariantQuery);
                    
                    // Bind parameter sepunya
                    $stmtVariants->bindParam(':product_id', $productId);
                    $stmtVariants->bindParam(':size', $vSize);
                    $stmtVariants->bindParam(':color', $vColor);
                    $stmtVariants->bindParam(':stock', $vStock);

                    // Bind khusus untuk UPDATE sahaja
                    if (!empty($vId)) {
                        $stmtVariants->bindParam(':id', $vId);
                    }

                    $stmtVariants->execute();
                }
            }

            $pdo->commit();
            echo json_encode(["status" => 'success', "message" => "Berjaya mengemaskini produk dan variasi!"]);

        // ==========================================
        // LOGIK PERINGKAT ACTION: ADD
        // ==========================================
        } else if ($action == "add") {
            $finalImgUrl = $imageUrlInDB ? $imageUrlInDB : 'uploads/default_product.jpg';

            $sqlInsertProduct = 'INSERT INTO products (name, description, price, image_url) 
                                 VALUES (:name, :desc, :price, :imgUrl)';
            $stmtInsert = $pdo->prepare($sqlInsertProduct);
            $stmtInsert->bindParam(':name', $name);
            $stmtInsert->bindParam(':desc', $description);
            $stmtInsert->bindParam(':price', $price);
            $stmtInsert->bindParam(':imgUrl', $finalImgUrl);
            
            if ($stmtInsert->execute()) {
                $newProductId = $pdo->lastInsertId();

                if (!empty($variants) && is_array($variants)) {
                    $sqlInsertVariant = 'INSERT INTO product_variants (product_id, size, color, stock) 
                                         VALUES (:product_id, :size, :color, :stock)';
                    $stmtVar = $pdo->prepare($sqlInsertVariant);

                    foreach ($variants as $variant) {
                        $vSize = $variant['size'] ?? '';
                        $vColor = $variant['color'] ?? '';
                        $vStock = !empty($variant['stock']) ? intval($variant['stock']) : 0;

                        $stmtVar->bindParam(':product_id', $newProductId);
                        $stmtVar->bindParam(':size', $vSize);
                        $stmtVar->bindParam(':color', $vColor);
                        $stmtVar->bindParam(':stock', $vStock);
                        $stmtVar->execute();
                    }
                }

                $pdo->commit();
                echo json_encode(["status" => 'success', "message" => "Berjaya menambah produk dan variasi baru!"]);
            } else {
                $pdo->rollBack();
                echo json_encode(["status" => 'error', "message" => "Gagal menambah produk baru."]);
            }

        } else {
            echo json_encode(["status" => 'error', "message" => "Aksi (action) tidak dikenali."]);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Ralat Database: " . $e->getMessage() . " pada baris " . $e->getLine()
        ]);
    }
    exit();
}
?>