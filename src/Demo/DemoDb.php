<?php
declare(strict_types=1);

namespace App\Demo;

/**
 * 給 Demo 模式用的 DB stub：
 * - transaction() 委派給 DemoStore（FillController submit 會呼叫）
 * - 其它方法（execute/scalar/fetchOne/fetchAll）為 no-op，避免 AuthController 的
 *   登入鎖定/紀錄、HomeController health 等對 db 的呼叫拋例外。
 */
class DemoDb
{
    public function __construct(private DemoStore $store) {}

    public function transaction(callable $fn)
    {
        return $this->store->transaction($fn);
    }

    public function execute(string $sql, array $binds = []): int { return 0; }
    public function scalar(string $sql, array $binds = []) { return 0; }
    public function fetchOne(string $sql, array $binds = []): ?array { return null; }
    public function fetchAll(string $sql, array $binds = []): array { return []; }
    public function insertReturningId(string $sql, array $binds = []): int { return 0; }
    public function version(): string { return 'Demo Mode (No Oracle)'; }
    public function getServerVersion(): string { return $this->version(); }
}
