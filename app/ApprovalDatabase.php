<?php
declare(strict_types=1);
namespace Pictures;
use PDO;
use PDOException;
use RuntimeException;
final class ApprovalDatabase {
    private static ?PDO $connection = null;
    public static function connection(): PDO {
        if (self::$connection instanceof PDO) return self::$connection;
        $file = dirname(__DIR__) . '/config/pics.local.php';
        if (!is_file($file)) throw new RuntimeException('فایل config/pics.local.php تنظیم نشده است.');
        $c = require $file;
        $user = $c['username'] ?? $c['uid'] ?? null;
        $pass = $c['password'] ?? $c['pwd'] ?? null;
        foreach (['server','database'] as $key) {
            if (!isset($c[$key]) || !is_string($c[$key]) || $c[$key] === '') throw new RuntimeException('تنظیمات دیتابیس Pics ناقص است.');
        }
        if (!is_string($user) || $user === '' || !is_string($pass)) throw new RuntimeException('نام کاربری یا رمز دیتابیس Pics تنظیم نشده است.');
        $dsn = sprintf('sqlsrv:Server=%s;Database=%s;TrustServerCertificate=%s;LoginTimeout=%d',
            $c['server'], $c['database'], !empty($c['trust_server_certificate']) ? '1' : '0', max(1,(int)($c['login_timeout'] ?? 5)));
        try {
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false
            ]);
        } catch (PDOException $e) {
            error_log('Pics approval DB connection failed: '.$e->getMessage());
            throw new RuntimeException('اتصال به دیتابیس Pics برقرار نشد.');
        }
        return self::$connection;
    }
}
