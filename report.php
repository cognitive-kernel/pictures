<?php
declare(strict_types=1);

use Pictures\Database;
use Pictures\ApprovalDatabase;

require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/ApprovalDatabase.php';

$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'unregistered', 'registered', 'approved', 'rejected', 'rejected_or_unregistered'], true)) $status = 'all';
$search = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($search, 'UTF-8') > 100) $search = mb_substr($search, 0, 100, 'UTF-8');

try {
    $source = Database::connection();
    $sql = "
        SELECT DISTINCT CONVERT(NVARCHAR(100), COALESCE(gp.Code, p.Number)) AS ProductCode, gp.Name AS ProductName
        FROM [SLS3].[Product] p
        LEFT JOIN [LGS3].[Part] gp ON gp.Code = p.Number
        WHERE p.state = 1
          AND (:searchEmpty = 1 OR CONVERT(NVARCHAR(100), COALESCE(gp.Code, p.Number)) LIKE :searchCodeLike OR gp.Name LIKE :searchNameLike)
        ORDER BY ProductCode
    ";
    $query = $source->prepare($sql);
    $like = '%' . $search . '%';
    $query->execute([':searchEmpty' => $search === '' ? 1 : 0, ':searchCodeLike' => $like, ':searchNameLike' => $like]);
    $products = $query->fetchAll(PDO::FETCH_ASSOC);

    $approvalPdo = ApprovalDatabase::connection();
    $approvalRows = $approvalPdo->query('SELECT ProductCode, IsApproved, ModifiedAt FROM dbo.ProductApproval')->fetchAll(PDO::FETCH_ASSOC);
    $approvalMap = [];
    foreach ($approvalRows as $row) $approvalMap[(string)$row['ProductCode']] = $row;

    $rows = [];
    $counts = ['all' => 0, 'registered' => 0, 'approved' => 0, 'rejected' => 0, 'rejected_or_unregistered' => 0];
    foreach ($products as $product) {
        $code = trim((string)$product['ProductCode']);
        $approval = $approvalMap[$code] ?? null;
        $isRegistered = $approval !== null;
        $isApproved = $isRegistered && (int)$approval['IsApproved'] === 1;
        $isRejected = $isRegistered && !$isApproved;

        $counts['all']++;
        if ($isRegistered) $counts['registered']++;
        if ($isApproved) $counts['approved']++;
        if ($isRejected) $counts['rejected']++;
        if (!$isApproved) $counts['rejected_or_unregistered']++;

        if ($status === 'unregistered' && $isRegistered) continue;
        if ($status === 'registered' && !$isRegistered) continue;
        if ($status === 'approved' && !$isApproved) continue;
        if ($status === 'rejected' && !$isRejected) continue;
        if ($status === 'rejected_or_unregistered' && $isApproved) continue;

        $rows[] = [
            'ProductCode' => $code,
            'ProductName' => (string)($product['ProductName'] ?? ''),
            'Status' => !$isRegistered ? 'ثبت‌نشده' : ($isApproved ? 'تأییدشده' : 'ردشده'),
            'ModifiedAt' => jalaliDateTime($approval['ModifiedAt'] ?? null),
        ];
    }

    if (($_GET['export'] ?? '') === 'csv') {
        $filename = 'product-approval-report-' . date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['کد کالا', 'نام کالا', 'وضعیت ثبت', 'آخرین ثبت/ویرایش']);
        foreach ($rows as $row) {
            $values = [$row['ProductCode'], $row['ProductName'], $row['Status'], $row['ModifiedAt']];
            foreach ($values as &$value) {
                if (preg_match('/^[=+@\-\t\r]/u', (string)$value)) $value = "'" . $value;
            }
            unset($value);
            fputcsv($out, $values);
        }
        fclose($out);
        exit;
    }
} catch (Throwable $exception) {
    error_log('Pictures approval report failed: ' . $exception->getMessage());
    http_response_code(500);
    $errorMessage = 'تهیه گزارش ناموفق بود. اتصال‌های راهکاران و Pics و وجود جدول ProductApproval را بررسی کنید.';
}
function reportUrl(array $changes = []): string {
    $params = $_GET;
    unset($params['export']);
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    return 'report.php?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>گزارش تأیید کالاها</title>
    <link rel="stylesheet" href="assets/app.css?v=20261125-mobile-cards-2">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div>
            <p class="eyebrow">RAHKARAN · PICS</p>
            <h1>گزارش تأیید کالاها</h1>
            <p class="subtitle">گزارش وضعیت تأیید کالاها و زمان آخرین تغییر</p>
        </div>
        <nav class="main-nav" aria-label="منوی اصلی">
            <a href="index.php">گالری کالاها</a>
            <a class="active" href="report.php">گزارش تأیید</a>
        </nav>
    </div>
</header>
<main class="container main-content">
    <?php if (isset($errorMessage)): ?>
        <section class="notice error" role="alert"><h2>خطا در تهیه گزارش</h2><p><?= e($errorMessage) ?></p></section>
    <?php else: ?>
        <section class="report-summary" aria-label="خلاصه گزارش">
            <div><span>همه کالاها</span><strong><?= number_format($counts['all']) ?></strong></div>
            <div><span>ثبت‌شده‌ها</span><strong><?= number_format($counts['registered']) ?></strong></div>
            <div><span>تأییدشده‌ها</span><strong><?= number_format($counts['approved']) ?></strong></div>
            <div><span>ردشده‌ها</span><strong><?= number_format($counts['rejected']) ?></strong></div>
            <div><span>ردشده یا ثبت‌نشده</span><strong><?= number_format($counts['rejected_or_unregistered']) ?></strong></div>
        </section>
        <form class="filters" method="get" action="report.php">
            <div class="filter-search">
                <label for="q">جستجو بر اساس کد یا نام کالا</label>
                <input id="q" name="q" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="کد یا بخشی از نام کالا">
            </div>
            <div class="filter-status">
                <label for="status">وضعیت ثبت کالا</label>
                <select id="status" name="status">
                    <option value="rejected_or_unregistered" <?= $status === 'rejected_or_unregistered' ? 'selected' : '' ?>>ردشده‌ها و تعیین‌تکلیف‌نشده‌ها</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>ردشده‌های ثبت‌شده</option>
                    <option value="unregistered" <?= $status === 'unregistered' ? 'selected' : '' ?>>تعیین‌تکلیف‌نشده‌ها</option>
                    <option value="registered" <?= $status === 'registered' ? 'selected' : '' ?>>ثبت‌شده‌ها (تأییدشده و ردشده)</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>تأییدشده‌ها</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه کالاها</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="report-link">نمایش گزارش</button>
                <a class="report-link" href="report.php">پاک کردن</a>
                <a class="report-link export-link" href="<?= e(reportUrl(['export' => 'csv'])) ?>">دریافت خروجی CSV</a>
            </div>
        </form>
        <section class="toolbar">
            <p>تعداد ردیف‌های گزارش: <?= number_format(count($rows)) ?></p>
            <p>خروجی CSV مطابق فیلترهای انتخاب‌شده تهیه می‌شود.</p>
        </section>
        <div class="table-wrap">
            <table class="report-table">
                <thead><tr><th>کد کالا</th><th>نام کالا</th><th>وضعیت ثبت</th><th>آخرین ثبت/ویرایش</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="4" class="empty">موردی برای نمایش وجود ندارد.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="کد کالا" class="product-number"><?= e($row['ProductCode']) ?></td>
                        <td data-label="نام کالا"><?= e($row['ProductName']) ?></td>
                        <td data-label="وضعیت ثبت"><span class="status-pill <?= $row['Status'] === 'تأییدشده' ? 'is-approved' : 'is-pending' ?>"><?= e($row['Status']) ?></span></td>
                        <td data-label="آخرین ثبت/ویرایش" class="approval-time"><?= e($row['ModifiedAt'] ?? 'ثبت نشده') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
<footer class="footer"><div class="container">گزارش تأیید کالاها · منبع کالاها: راهکاران · وضعیت‌ها: Pics</div></footer>
</body>
</html>
