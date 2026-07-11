<?php
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Abaikan baris komen (#)
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Pisahkan nama key dan value
        list($name, $value) = explode('=', $line, 2);
        
        $name = trim($name);
        $value = trim($value);

        // Buang tanda petik jika ada (cth: "value" atau 'value')
        $value = trim($value, '"\'');

        // Masukkan ke dalam PHP global variables
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
    return true;
}
