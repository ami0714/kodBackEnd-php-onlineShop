<?php
function buat_token($data_user) {
    $secret_key = "KunciRahsia_Mvt2026"; // Wajib rahsia!
    
    // 1. Header
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    
    // 2. Payload (Data)
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($data_user)));
    
    // 3. Signature (Kunci mangga guna HMAC SHA256)
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret_key, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    // Cantum semua dengan titik (.)
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}
// ------------------------------


// Fungsi untuk semak dan pecahkan token JWT
function decode_token($token) {
    $secret_key = "KunciRahsia_Mvt2026"; // Mesti sama dengan kunci masa jana token!

    // 1. Pecahkan token kepada 3 bahagian (Header, Payload, Signature)
    $bahagian = explode('.', $token);
    if (count($bahagian) !== 3) {
        return false; // Token rosak atau format salah
    }

    list($base64Header, $base64Payload, $base64Signature) = $bahagian;

    // 2. Kita buat balik signature versi server guna data header + payload yang diterima
    $signature_semakan = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret_key, true);
    $base64Signature_semakan = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature_semakan));

    // 3. Bandingkan signature yang dihantar oleh React dengan signature semakan server
    if ($base64Signature !== $base64Signature_semakan) {
        return false; // Token palsu! Ada orang cuba ubah suai data
    }

    // 4. Kalau signature betul/sah, barulah kita decode data Payload tu jadi Array PHP
    $payload_json = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload));
    return json_decode($payload_json, true); // Pulangkan data user (id, nama, dll)
}

