<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function queryUrl(array $changes = []): string
{
    $params = $_GET;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return '?' . http_build_query($params);
}

/**
 * Format SQL Server Gregorian datetime values as Persian (Jalali) date/time.
 * This implementation does not require the PHP intl extension.
 */
function jalaliDateTime(mixed $value): string
{
    if ($value === null || trim((string)$value) === '') {
        return 'ثبت نشده';
    }

    try {
        $date = new DateTimeImmutable((string)$value);
    } catch (Throwable) {
        return (string)$value;
    }

    $gy = (int)$date->format('Y');
    $gm = (int)$date->format('n');
    $gd = (int)$date->format('j');
    $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = (365 * $gy)
        + intdiv($gy2 + 3, 4)
        - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400)
        - 80
        + $gd
        + $gDaysInMonth[$gm - 1];

    $jy += 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }

    $formatted = sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $date->format('H:i:s'));
    return strtr($formatted, '0123456789', '۰۱۲۳۴۵۶۷۸۹');
}
