<?php
declare(strict_types=1);

namespace Pictures;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $localConfig = dirname(__DIR__) . '/config/database.local.php';
        if (!is_file($localConfig)) {
            throw new RuntimeException(
                'تنظیمات اتصال موجود نیست. فایل config/database.local.php.example را کپی و تنظیم کنید.'
            );
        }

        $config = require $localConfig;
        foreach (['server', 'database', 'username', 'password'] as $key) {
            if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') {
                throw new RuntimeException('تنظیمات اتصال دیتابیس ناقص است.');
            }
        }

        $trustCertificate = !empty($config['trust_server_certificate']) ? '1' : '0';
        $timeout = max(1, (int)($config['login_timeout'] ?? 5));
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=%s;LoginTimeout=%d',
            $config['server'],
            $config['database'],
            $trustCertificate,
            $timeout
        );

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('Pictures DB connection failed: ' . $exception->getMessage());
            throw new RuntimeException(
                'اتصال به دیتابیس برقرار نشد. تنظیمات اتصال و نصب افزونه pdo_sqlsrv را بررسی کنید.'
            );
        }

        return self::$connection;
    }
}
