<?php
declare(strict_types=1);

use Pictures\Database;

require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Database.php';

const PAGE_SIZE = 25;

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page !== false && $page !== null && $page > 0) ? $page : 1;

$allowedSorts = ['number' => 'COALESCE(gp.Code, p.Number)', 'name' => 'COALESCE(gp.Name, p.Name)'];
$sort = (string)($_GET['sort'] ?? 'number');
if (!array_key_exists($sort, $allowedSorts)) $sort = 'number';
$direction = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$orderBy = $allowedSorts[$sort] . ' ' . strtoupper($direction) . ', p.ProductID ASC';

// The requested attachment-first query is the common source for image data.
$attachmentQuery = "
    SELECT gp.Code, gp.Name, pa.FileContent
    FROM [SLS3].[ProductAttachment] pa
    LEFT JOIN [SLS3].[Product] p ON pa.ProductRef = p.ProductID
    LEFT JOIN [LGS3].[Part] gp ON p.Number = gp.Code
";

$products = [];
$total = 0;
$allTotal = 0;
$errorMessage = null;
$totalPages = 1;
$search = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($search, 'UTF-8') > 100) {
    $search = mb_substr($search, 0, 100, 'UTF-8');
}
$imageFilter = (string)($_GET['image'] ?? 'all');
if (!in_array($imageFilter, ['all', 'with', 'without'], true)) {
    $imageFilter = 'all';
}

try {
    $pdo = Database::connection();
    $allTotal = (int)$pdo->query('SELECT COUNT_BIG(*) FROM [SLS3].[Product] p')->fetchColumn();

    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(CONVERT(NVARCHAR(255), COALESCE(gp.Code, p.Number)) LIKE :searchNumber OR COALESCE(gp.Name, p.Name) LIKE :searchName)';
        $params[':searchNumber'] = '%' . $search . '%';
        $params[':searchName'] = '%' . $search . '%';
    }
    if ($imageFilter === 'with') {
        $where[] = 'EXISTS (SELECT 1 FROM (' . $attachmentQuery . ') imageData WHERE imageData.Code = COALESCE(gp.Code, p.Number) AND imageData.FileContent IS NOT NULL)';
    } elseif ($imageFilter === 'without') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM (' . $attachmentQuery . ') imageData WHERE imageData.Code = COALESCE(gp.Code, p.Number) AND imageData.FileContent IS NOT NULL)';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $countStatement = $pdo->prepare('SELECT COUNT_BIG(*) FROM [SLS3].[Product] p LEFT JOIN [LGS3].[Part] gp ON p.Number = gp.Code' . $whereSql);
    foreach ($params as $key => $value) {
        $countStatement->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStatement->execute();
    $total = (int)$countStatement->fetchColumn();
    $totalPages = max(1, (int)ceil($total / PAGE_SIZE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * PAGE_SIZE;

    $sql = "
        SELECT p.ProductID,
               COALESCE(gp.Code, p.Number) AS Number,
               COALESCE(gp.Name, p.Name) AS Name,
               CASE WHEN img.FileContent IS NULL THEN 0 ELSE 1 END AS HasImage
        FROM [SLS3].[Product] p
        LEFT JOIN [LGS3].[Part] gp ON p.Number = gp.Code
        OUTER APPLY (
            SELECT TOP (1) imageData.FileContent
            FROM ({$attachmentQuery}) imageData
            WHERE imageData.Code = COALESCE(gp.Code, p.Number)
              AND imageData.FileContent IS NOT NULL
        ) img
        {$whereSql}
        ORDER BY {$orderBy}
        OFFSET :offset ROWS FETCH NEXT :pageSize ROWS ONLY
    ";
    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->bindValue(':pageSize', PAGE_SIZE, PDO::PARAM_INT);
    $statement->execute();
    $products = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Pictures page failed: ' . $exception->getMessage());
    $errorMessage = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'دریافت اطلاعات با خطا مواجه شد. گزارش خطا در لاگ سرور ثبت شده است.';
}

function sortLink(string $column, string $label, string $currentSort, string $currentDirection): string
{
    $nextDirection = ($column === $currentSort && $currentDirection === 'asc') ? 'desc' : 'asc';
    $marker = $column === $currentSort ? ($currentDirection === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a class="sort-link" href="' . e(queryUrl(['sort' => $column, 'dir' => $nextDirection, 'page' => 1])) . '">' .
        e($label . $marker) . '</a>';
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>گالری تصاویر کالاها</title>
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div>
            <p class="eyebrow">RAHKARAN · SQL SERVER</p>
            <h1>گالری تصاویر کالاها</h1>
            <p class="subtitle">مشاهده کالاها و تصاویر پیوست‌شده در سیستم راهکاران</p>
        </div>
        <div class="total-box"><span>تعداد کل کالاها</span><strong><?= e(number_format($allTotal)) ?></strong></div>
    </div>
</header>

<main class="container main-content">
    <?php if ($errorMessage !== null): ?>
        <section class="notice error" role="alert">
            <h2>خطا در دریافت اطلاعات</h2>
            <p><?= e($errorMessage) ?></p>
            <p class="hint">در XAMPP بررسی کنید افزونه pdo_sqlsrv فعال باشد و فایل config/database.local.php تنظیم شده باشد.</p>
        </section>
    <?php else: ?>
        <form class="filters" method="get" action="index.php">
            <div class="filter-search">
                <label for="q">جستجو بر اساس کد یا نام کالا</label>
                <input id="q" name="q" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="کد یا بخشی از نام کالا">
            </div>
            <div class="filter-status">
                <label for="image">وضعیت تصویر</label>
                <select id="image" name="image">
                    <option value="all" <?= $imageFilter === 'all' ? 'selected' : '' ?>>همه کالاها</option>
                    <option value="with" <?= $imageFilter === 'with' ? 'selected' : '' ?>>فقط کالاهای دارای تصویر</option>
                    <option value="without" <?= $imageFilter === 'without' ? 'selected' : '' ?>>فقط کالاهای بدون تصویر</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="report-link">اعمال فیلتر</button>
                <a class="report-link" href="index.php">پاک کردن</a>
            </div>
        </form>
        <section class="toolbar" aria-label="اطلاعات فهرست">
            <p>نمایش <?= e(number_format(count($products))) ?> کالا از <?= e(number_format($total)) ?> نتیجه</p>
            <p>صفحه <?= e(number_format($page)) ?> از <?= e(number_format($totalPages)) ?></p>
            <p><a class="report-link" href="no-images.php">گزارش کالاهای بدون تصویر و خروجی Excel</a></p>
        </section>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th class="image-col">تصویر</th>
                    <th><?= sortLink('number', 'شماره کالا', $sort, $direction) ?></th>
                    <th><?= sortLink('name', 'نام کالا', $sort, $direction) ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$products): ?><tr><td colspan="3" class="empty">رکوردی برای نمایش وجود ندارد.</td></tr><?php endif; ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td class="image-col">
                            <?php if ((int)$product['HasImage'] === 1): ?>
                                <button class="thumbnail-button js-open-image" type="button"
                                        data-image="image.php?id=<?= rawurlencode((string)$product['ProductID']) ?>"
                                        data-title="<?= e($product['Name'] ?? '') ?>"
                                        aria-label="نمایش تصویر <?= e($product['Name'] ?? 'کالا') ?>">
                                    <img src="image.php?id=<?= rawurlencode((string)$product['ProductID']) ?>&thumb=1"
                                         alt="<?= e($product['Name'] ?? 'تصویر کالا') ?>" loading="lazy">
                                </button>
                            <?php else: ?><span class="no-image">بدون تصویر</span><?php endif; ?>
                        </td>
                        <td class="product-number"><?= e($product['Number'] ?? '—') ?></td>
                        <td><?= e($product['Name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="صفحه‌بندی">
                <?php if ($page > 1): ?><a href="<?= e(queryUrl(['page' => $page - 1])) ?>">قبلی</a>
                <?php else: ?><span class="disabled">قبلی</span><?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                if ($start > 1) {
                    echo '<a href="' . e(queryUrl(['page' => 1])) . '">1</a>';
                    if ($start > 2) echo '<span class="ellipsis">…</span>';
                }
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $page): ?><span class="current" aria-current="page"><?= e($i) ?></span>
                    <?php else: ?><a href="<?= e(queryUrl(['page' => $i])) ?>"><?= e($i) ?></a><?php endif; ?>
                <?php endfor;
                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<span class="ellipsis">…</span>';
                    echo '<a href="' . e(queryUrl(['page' => $totalPages])) . '">' . e($totalPages) . '</a>';
                }
                ?>
                <?php if ($page < $totalPages): ?><a href="<?= e(queryUrl(['page' => $page + 1])) ?>">بعدی</a>
                <?php else: ?><span class="disabled">بعدی</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<div class="lightbox" id="image-lightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="نمایش تصویر بزرگ">
    <button type="button" class="lightbox-close" id="lightbox-close" aria-label="بستن تصویر">×</button>
    <p class="lightbox-title" id="lightbox-title"></p>
    <div class="lightbox-stage"><img id="lightbox-image" alt=""></div>
    <p class="lightbox-hint">برای بستن، بیرون تصویر کلیک کنید یا کلید Escape را بزنید.</p>
</div>
<footer class="footer"><div class="container">گالری تصاویر کالاها · اتصال فقط خواندنی</div></footer>
</body>
</html>
