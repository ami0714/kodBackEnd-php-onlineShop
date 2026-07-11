<?php
// Memulakan buffer untuk menangkap sebarang output tidak sengaja
ob_start();

// Matikan paparan error agar tidak mengganggu JSON


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

include 'env_loader.php';

loadEnv(__DIR__ . '/.env');



$servername = $_ENV['servername'];
$username_db =  $_ENV['username_db'];
$password_db = $_ENV['password_db'];
$dbname = $_ENV['dbname'];
$dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";


try {
    $pdo = new PDO($dsn, $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
   header('Content-Type: application/json');
     echo json_encode(["status" => "error", "message" => $e->getMessage()]);
     exit;
}

// if (isset($pdo)) {
//     echo json_encode(["status" => "success", "message" => "Connection Successful"]);
// } else {
//     echo json_encode(["status" => "error", "message" => "Connection Failed"]);
//     exit;
// }




// $pdo will always be set if no exception is thrown, so this check is unnecessary

// Fungsi untuk menghantar JSON yang bersih (Menghalang iklan Awardspace)
ob_clean();
?>