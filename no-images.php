<?php
declare(strict_types=1);

use Pictures\Database;

require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Database.php';

const PAGE_SIZE = 50;

try {
    $pdo = Database::connection();
    $attachmentQuery = "
        SELECT gp.Code, gp.Name, pa.FileContent
        FROM [SLS3].[ProductAttachment] pa
        LEFT JOIN [SLS3].[Product] p ON pa.ProductRef = p.ProductID
        LEFT JOIN [LGS3].[Part] gp ON p.Number = gp.Code
    ";
    $baseSql = "
        FROM [SLS3].[Product] p
        LEFT JOIN [LGS3].[Part] gp ON p.Number = gp.Code
        WHERE NOT EXISTS (
            SELECT 1
            FROM ({$attachmentQuery}) imageData
            WHERE imageData.Code = COALESCE(gp.Code, p.Number)
              AND imageData.FileContent IS NOT NULL
        )
    ";

    if (($_GET['export'] ?? '') === 'csv') {
        $statement = $pdo->query("
            SELECT COALESCE(gp.Code, p.Number) AS Number, COALESCE(gp.Name, p.Name) AS Name
            {$baseSql}
            ORDER BY p.Number, p.ProductID
        ");
        $filename = 'products-without-images-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('ایجاد فایل خروجی ممکن نشد.');
        }
        // BOM helps Excel detect UTF-8 and display Persian correctly.
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['شماره کالا', 'نام کالا']);
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['Number'] ?? '', $row['Name'] ?? '']);
        }
        fclose($output);
        exit;
    }

    $total = (int)$pdo->query("SELECT COUNT_BIG(*) {$baseSql}")->fetchColumn();
    $totalPages = max(1, (int)ceil($total / PAGE_SIZE));
    $pageNumber = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $pageNumber = ($pageNumber !== false && $pageNumber !== null && $pageNumber > 0) ? $pageNumber : 1;
    $pageNumber = min($pageNumber, $totalPages);
    $offset = ($pageNumber - 1) * PAGE_SIZE;

    $statement = $pdo->prepare("
        SELECT COALESCE(gp.Code, p.Number) AS Number, COALESCE(gp.Name, p.Name) AS Name
        {$baseSql}
        ORDER BY p.Number, p.ProductID
        OFFSET :offset ROWS FETCH NEXT :pageSize ROWS ONLY
    ");
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->bindValue(':pageSize', PAGE_SIZE, PDO::PARAM_INT);
    $statement->execute();
    $products = $statement->fetchAll(PDO::FETCH_ASSOC);
    $errorMessage = null;
} catch (Throwable $exception) {
    error_log('Pictures no-image report failed: ' . $exception->getMessage());
    $errorMessage = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'دریافت فهرست کالاهای بدون تصویر با خطا مواجه شد. گزارش خطا در لاگ سرور ثبت شده است.';
    $total = 0;
    $totalPages = 1;
    $pageNumber = 1;
    $products = [];
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>کالاهای بدون تصویر</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div>
            <p class="eyebrow">RAHKARAN · SQL SERVER</p>
            <h1>فهرست کالاهای بدون تصویر</h1>
            <p class="subtitle">کالاهایی که هیچ پیوست تصویری غیرخالی ندارند</p>
        </div>
        <div class="total-box"><span>تعداد کالاهای بدون تصویر</span><strong><?= e(number_format($total)) ?></strong></div>
    </div>
</header>
<main class="container main-content">
    <section class="toolbar" aria-label="عملیات گزارش">
        <p>صفحه <?= e(number_format($pageNumber)) ?> از <?= e(number_format($totalPages)) ?></p>
        <p><a class="report-link" href="index.php">بازگشت به گالری</a> &nbsp; <a class="report-link" href="no-images.php?export=csv">خروجی CSV برای Excel</a></p>
    </section>
    <?php if ($errorMessage !== null): ?>
        <section class="notice error" role="alert">
            <h2>خطا در دریافت اطلاعات</h2>
            <p><?= e($errorMessage) ?></p>
        </section>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ردیف</th><th>شماره کالا</th><th>نام کالا</th></tr></thead>
                <tbody>
                <?php if (!$products): ?><tr><td colspan="3" class="empty">کالای بدون تصویر پیدا نشد.</td></tr><?php endif; ?>
                <?php foreach ($products as $index => $product): ?>
                    <tr>
                        <td><?= e(number_format($offset + $index + 1)) ?></td>
                        <td class="product-number"><?= e($product['Number'] ?? '—') ?></td>
                        <td><?= e($product['Name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="صفحه‌بندی">
                <?php if ($pageNumber > 1): ?><a href="?page=<?= e($pageNumber - 1) ?>">قبلی</a>
                <?php else: ?><span class="disabled">قبلی</span><?php endif; ?>
                <?php
                $start = max(1, $pageNumber - 2);
                $end = min($totalPages, $pageNumber + 2);
                if ($start > 1) {
                    echo '<a href="?page=1">1</a>';
                    if ($start > 2) echo '<span class="ellipsis">…</span>';
                }
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pageNumber): ?><span class="current" aria-current="page"><?= e($i) ?></span>
                    <?php else: ?><a href="?page=<?= e($i) ?>"><?= e($i) ?></a><?php endif; ?>
                <?php endfor;
                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<span class="ellipsis">…</span>';
                    echo '<a href="?page=' . e($totalPages) . '">' . e($totalPages) . '</a>';
                }
                ?>
                <?php if ($pageNumber < $totalPages): ?><a href="?page=<?= e($pageNumber + 1) ?>">بعدی</a>
                <?php else: ?><span class="disabled">بعدی</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<footer class="footer"><div class="container">گزارش کالاهای بدون تصویر · اتصال فقط خواندنی</div></footer>
</body>
</html>
