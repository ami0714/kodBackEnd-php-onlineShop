<?php
// ... (Header CORS awak kekal macam biasa)
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
include 'tokenJana.php'; // Pastikan tokenJana.php ada dalam folder yang sama dan fungsi buat_token() serta decode_token() ada di dalamnya

// --- FUNGSI NATIVE JANA JWT --- token ni nanti akan dihantar ke React, React pulak simpan dalam localStorage


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_clean();
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            
            // --- JANA TOKEN DI SINI --- payload adalah data yang kita nak simpan dalam token, contoh id, nama, masa login, dsb. Jangan simpan password atau data sensitif lain!
            $payload = [ //paylaod adalah data yang nak dimasukkan dalam token dan akan dihantar melaluo json encode
                "id" => $user['id'],
                "nama" => $user['username'],
                "role" => $user['role'],
                "masa_login" => time()
            ];
            
            $token = buat_token($payload); // Panggil fungsi kat atas tadi fungsi buat_token() untuk jana token berdasarkan payload yang kita buat. Token ni nanti akan dihantar ke React, React pulak simpan dalam localStorage atau state management.
            // --------------------------

            // Hantar token ni ke React
            echo json_encode([
                "status" => "success", 
                "message" => "Login berjaya", 
                "token" => $token, // <-- Token disertakan
                "user" => $user['username'],
                "role" => $user['role']
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Username atau Password salah!" , "datauser" => $email]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
    exit;
}
?>