<?php
declare(strict_types=1);

use Pictures\Database;

require_once __DIR__ . '/app/Database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    http_response_code(400);
    exit('Invalid image identifier.');
}

try {
    $pdo = Database::connection();
    $statement = $pdo->prepare(
        'SELECT TOP (1) pa.FileContent
         FROM [SLS3].[ProductAttachment] pa
         WHERE pa.ProductRef = :productId AND pa.FileContent IS NOT NULL
         ORDER BY pa.ProductRef'
    );
    $statement->bindValue(':productId', $id, PDO::PARAM_INT);
    $statement->execute();
    $content = $statement->fetchColumn();

    if ($content === false || $content === null || $content === '') {
        http_response_code(404);
        exit('Image not found.');
    }
    if (is_resource($content)) $content = stream_get_contents($content);
    if (!is_string($content) || $content === '') {
        http_response_code(404);
        exit('Image not found.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($content) ?: 'application/octet-stream';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
    if (!in_array($mime, $allowedTypes, true)) {
        http_response_code(415);
        exit('Unsupported image format.');
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($content));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');
    echo $content;
} catch (Throwable $exception) {
    error_log('Pictures image endpoint failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load image.');
}
