<?php
declare(strict_types=1);

use Pictures\Database;

require_once __DIR__ . '/app/Database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    http_response_code(400);
    exit('Invalid image identifier.');
}
$isThumbnail = isset($_GET['thumb']) && $_GET['thumb'] === '1';

try {
    $pdo = Database::connection();
    $statement = $pdo->prepare(
        'SELECT TOP (1) pa.FileContent
         FROM [SLS3].[ProductAttachment] pa
         LEFT JOIN [SLS3].[Product] p ON pa.ProductRef = p.ProductID
         LEFT JOIN [LGS3].[Part] gp ON p.Number = gp.Code
         WHERE p.ProductID = :productId AND pa.FileContent IS NOT NULL
         ORDER BY pa.ProductRef'
    );
    $statement->bindValue(':productId', $id, PDO::PARAM_INT);
    $statement->execute();
    $content = $statement->fetchColumn();

    if ($content === false || $content === null || $content === '') {
        http_response_code(404);
        exit('Image not found.');
    }
    if (is_resource($content)) {
        $content = stream_get_contents($content);
    }
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

    // Generate lightweight JPEG thumbnails when GD is available.
    if ($isThumbnail && extension_loaded('gd')) {
        $source = @imagecreatefromstring($content);
        if ($source !== false) {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth > 0 && $sourceHeight > 0) {
                $scale = min(1, 180 / $sourceWidth, 140 / $sourceHeight);
                $targetWidth = max(1, (int)round($sourceWidth * $scale));
                $targetHeight = max(1, (int)round($sourceHeight * $scale));
                $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
                $white = imagecolorallocate($thumbnail, 255, 255, 255);
                imagefill($thumbnail, 0, 0, $white);
                imagecopyresampled(
                    $thumbnail, $source, 0, 0, 0, 0,
                    $targetWidth, $targetHeight, $sourceWidth, $sourceHeight
                );
                header('Content-Type: image/jpeg');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, max-age=300');
                imagejpeg($thumbnail, null, 78);
                imagedestroy($thumbnail);
                imagedestroy($source);
                exit;
            }
            imagedestroy($source);
        }
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
