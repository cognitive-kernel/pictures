<?php
declare(strict_types=1);
use Pictures\ApprovalDatabase;
require_once __DIR__ . '/app/ApprovalDatabase.php';
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['success'=>false,'message'=>'روش درخواست مجاز نیست.']);
$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['approval_csrf']) || !hash_equals($_SESSION['approval_csrf'], $csrf)) respond(403, ['success'=>false,'message'=>'اعتبار درخواست منقضی شده است؛ صفحه را تازه‌سازی کنید.']);
$code = trim((string)($_POST['code'] ?? ''));
$approved = filter_var($_POST['approved'] ?? null, FILTER_VALIDATE_INT);
if ($code === '' || mb_strlen($code,'UTF-8') > 100 || !in_array($approved,[0,1],true)) respond(422, ['success'=>false,'message'=>'کد کالا یا وضعیت تأیید معتبر نیست.']);
try {
    $pdo = ApprovalDatabase::connection();
    $pdo->beginTransaction();
    $q = $pdo->prepare('UPDATE dbo.ProductApproval WITH (UPDLOCK, SERIALIZABLE) SET IsApproved=:approved, ModifiedAt=SYSDATETIME() WHERE ProductCode=:code');
    $q->execute([':approved'=>$approved, ':code'=>$code]);
    if ($q->rowCount() === 0) {
        $q = $pdo->prepare('INSERT INTO dbo.ProductApproval (ProductCode,IsApproved,CreatedAt,ModifiedAt) VALUES (:code,:approved,SYSDATETIME(),SYSDATETIME())');
        $q->execute([':code'=>$code, ':approved'=>$approved]);
    }
    $q = $pdo->prepare('SELECT CONVERT(varchar(19),ModifiedAt,120) FROM dbo.ProductApproval WHERE ProductCode=:code');
    $q->execute([':code'=>$code]);
    $modified = $q->fetchColumn();
    $pdo->commit();
    respond(200, ['success'=>true,'modified_at'=>$modified]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Pictures approval save failed: '.$e->getMessage());
    respond(500, ['success'=>false,'message'=>'ذخیره وضعیت تأیید در دیتابیس Pics ناموفق بود.']);
}
