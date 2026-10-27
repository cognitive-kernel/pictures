<?php
declare(strict_types=1);

require_once __DIR__ . '/app/helpers.php';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>گزارش کالاهای بدون تصویر</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div>
            <p class="eyebrow">RAHKARAN · SQL SERVER</p>
            <h1>گزارش کالاهای بدون تصویر</h1>
            <p class="subtitle">این گزارش با منبع دادهٔ فعلی قابل تولید نیست.</p>
        </div>
    </div>
</header>
<main class="container main-content">
    <section class="notice">
        <h2>محدودیت کوئری انتخاب‌شده</h2>
        <p>منبع اصلی پروژه فقط از کوئری پیوست‌ها استفاده می‌کند. کالایی که هیچ رکوردی در جدول پیوست‌ها ندارد، در این منبع وجود ندارد و شناسایی آن بدون استفاده از جدول کالاها به‌عنوان منبع مستقل ممکن نیست.</p>
        <p><a class="report-link" href="index.php">بازگشت به گالری تصاویر</a></p>
    </section>
</main>
<footer class="footer"><div class="container">گالری تصاویر کالاها · اتصال فقط خواندنی</div></footer>
</body>
</html>
