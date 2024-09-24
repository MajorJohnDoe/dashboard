<?php
// File: save-audio.php
header('Content-Type: application/json');

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = substr($val, 0, -1);
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

$max_size = return_bytes(ini_get('upload_max_filesize'));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_max_size'])) {
    echo json_encode(['max_size' => $max_size]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['audio'])) {
        throw new Exception('Invalid request');
    }

    $uploadDir = __DIR__ . '/../../../user_upload_temp/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    $fileName = uniqid('audio_') . '.wav';
    $filePath = $uploadDir . $fileName;

    if ($_FILES['audio']['size'] > $max_size) {
        throw new Exception('File size exceeds the maximum allowed size');
    }

    if (!move_uploaded_file($_FILES['audio']['tmp_name'], $filePath)) {
        throw new Exception('Failed to save audio file');
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Audio saved successfully',
        'fileName' => $fileName,
        'filePath' => $filePath
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>