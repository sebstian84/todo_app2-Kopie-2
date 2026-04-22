<?php
$data_dir = __DIR__ . '/../api/data/';
$encryption_key = "todo_secret_key_32_chars_long_!!!";

function decrypt($data, $key) {
    $data = base64_decode($data);
    $iv_len = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_len);
    $encrypted = substr($data, $iv_len);
    return openssl_decrypt($encrypted, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
}

$encrypted = file_get_contents($data_dir . 'users.json');
echo decrypt($encrypted, $encryption_key);
