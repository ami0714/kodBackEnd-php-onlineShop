<?php
function compressAndResizeImage($sourcePath, $destinationPath, $maxWidth = 800, $quality = 75) {
    // 1. Ambil info imej (lebar, tinggi, jenis jenis fail)
    $imgInfo = getimagesize($sourcePath);
    if (!$imgInfo) return false;

    $mime = $imgInfo['mime'];
    $origWidth = $imgInfo[0];
    $origHeight = $imgInfo[1];

    // 2. Kira resolusi baru (Kekalkan aspect ratio supaya gambar tak penyek)
    if ($origWidth > $maxWidth) {
    $newWidth = (int)$maxWidth;
    // Letak (int)round(...) supaya hasil bahagi tak jadi perpuluhan panjang (float)
    $newHeight = (int)round(($origHeight / $origWidth) * $maxWidth);
} else {
    $newWidth = (int)$origWidth;
    $newHeight = (int)$origHeight;
}

    // 3. Cipta imej berdasarkan jenis fail asal
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false; // Fail bukan jenis gambar yang disokong
    }

    // 4. Cipta kanvas kosong baru dengan saiz yang dikecilkan
    $newImage = imagecreatetruecolor((int)$newWidth, (int)$newHeight);

    // 5. Kekalkan kesan transparent (lutsinar) jika imej jenis PNG atau WEBP
    if ($mime == 'image/png' || $mime == 'image/webp') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }

    // 6. Salin dan re-sample imej asal ke kanvas baru (proses resize berlaku di sini)
    imagecopyresampled(
    $newImage, 
    $image, 
    0, 0, 0, 0, 
    (int)$newWidth, 
    (int)$newHeight, 
    (int)$origWidth, 
    (int)$origHeight
);

    // 7. Simpan imej ke destinasi dengan mampatan (compression)
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            imagejpeg($newImage, $destinationPath, $quality); // Kualiti 0 - 100
            break;
        case 'image/png':
            // PNG guna skala compression 0 (tiada mampatan) hingga 9 (maksimum mampatan)
            $pngQuality = round((100 - $quality) / 10); 
            imagepng($newImage, $destinationPath, $pngQuality);
            break;
        case 'image/webp':
            imagewebp($newImage, $destinationPath, $quality);
            break;
    }

    // 8. Bersihkan memori server
    imagedestroy($image);
    imagedestroy($newImage);

    return true;
} ?>