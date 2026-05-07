<?php
/**
 * 簡易 PSR-4 autoloader (避免強制需要 composer install)
 * 命名空間 App\ → src/
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 若有 composer 自動載入則一併載入
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}
