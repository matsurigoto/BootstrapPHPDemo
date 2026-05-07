<?php
declare(strict_types=1);

namespace App\Db;

/**
 * 簡化的 OCI8 連線包裝。
 * - 全部 SQL 請使用具名綁定 (:name)；對 EAV 大量查詢呼叫 fetchAll/fetchOne。
 * - 交易：begin / commit / rollback 或 transaction(closure)。
 */
class OciConnection
{
    /** @var resource|false */
    private $conn = false;
    private bool $inTx = false;
    /** @var array<string,mixed> 持有目前 statement 綁定值的參考儲存 */
    private array $bindStore = [];

    public function __construct(private array $cfg) {}

    /**
     * @return resource
     */
    public function resource()
    {
        if ($this->conn === false) {
            $conn = @oci_connect(
                $this->cfg['username'],
                $this->cfg['password'],
                $this->cfg['connection_string'],
                $this->cfg['charset'] ?? 'AL32UTF8',
                $this->cfg['session_mode'] ?? OCI_DEFAULT
            );
            if ($conn === false) {
                $err = oci_error();
                throw new \RuntimeException('Oracle 連線失敗: ' . ($err['message'] ?? 'unknown'));
            }
            $this->conn = $conn;
        }
        return $this->conn;
    }

    /**
     * 執行 DML。$binds 可包含特殊型別：
     *   ['name' => ['value' => $v, 'type' => SQLT_CHR, 'length' => 4000]]
     *   或 ['name' => $value]
     * 回傳受影響列數。
     *
     * @param array<string,mixed> $binds
     */
    public function execute(string $sql, array $binds = []): int
    {
        $stmt = $this->prepare($sql, $binds);
        $mode = $this->inTx ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS;
        if (!@oci_execute($stmt, $mode)) {
            $err = oci_error($stmt);
            oci_free_statement($stmt);
            throw new \RuntimeException('SQL 失敗: ' . ($err['message'] ?? '') . ' | ' . $sql);
        }
        $rows = oci_num_rows($stmt);
        oci_free_statement($stmt);
        return (int) $rows;
    }

    /**
     * 取得單一欄位（如序列 nextval）。
     *
     * @param array<string,mixed> $binds
     * @return mixed
     */
    public function scalar(string $sql, array $binds = [])
    {
        $row = $this->fetchOne($sql, $binds);
        if ($row === null) return null;
        return reset($row);
    }

    /**
     * @param array<string,mixed> $binds
     * @return array<int,array<string,mixed>>
     */
    public function fetchAll(string $sql, array $binds = []): array
    {
        $stmt = $this->prepare($sql, $binds);
        if (!@oci_execute($stmt, OCI_DEFAULT)) {
            $err = oci_error($stmt);
            oci_free_statement($stmt);
            throw new \RuntimeException('SQL 失敗: ' . ($err['message'] ?? '') . ' | ' . $sql);
        }
        $rows = [];
        while (($r = oci_fetch_assoc($stmt)) !== false) {
            $rows[] = $this->normalizeRow($r);
        }
        oci_free_statement($stmt);
        return $rows;
    }

    /**
     * @param array<string,mixed> $binds
     * @return array<string,mixed>|null
     */
    public function fetchOne(string $sql, array $binds = []): ?array
    {
        $stmt = $this->prepare($sql, $binds);
        if (!@oci_execute($stmt, OCI_DEFAULT)) {
            $err = oci_error($stmt);
            oci_free_statement($stmt);
            throw new \RuntimeException('SQL 失敗: ' . ($err['message'] ?? '') . ' | ' . $sql);
        }
        $r = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        return $r === false ? null : $this->normalizeRow($r);
    }

    /**
     * INSERT 並透過 RETURNING 取回新 ID。
     */
    public function insertReturningId(string $sql, array $binds, string $idBindName = 'new_id'): int
    {
        $stmt = $this->prepare($sql, $binds, [$idBindName => ['type' => SQLT_INT, 'length' => -1]]);
        $newId = 0;
        oci_bind_by_name($stmt, ':' . $idBindName, $newId, -1, SQLT_INT);
        $mode = $this->inTx ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS;
        if (!@oci_execute($stmt, $mode)) {
            $err = oci_error($stmt);
            oci_free_statement($stmt);
            throw new \RuntimeException('SQL 失敗: ' . ($err['message'] ?? '') . ' | ' . $sql);
        }
        oci_free_statement($stmt);
        return (int)$newId;
    }

    public function begin(): void
    {
        $this->inTx = true;
    }

    public function commit(): void
    {
        if ($this->conn === false) return;
        if (!@oci_commit($this->conn)) {
            $err = oci_error($this->conn);
            throw new \RuntimeException('Commit 失敗: ' . ($err['message'] ?? ''));
        }
        $this->inTx = false;
    }

    public function rollback(): void
    {
        if ($this->conn === false) return;
        @oci_rollback($this->conn);
        $this->inTx = false;
    }

    /**
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaction(callable $fn)
    {
        $this->begin();
        try {
            $r = $fn($this);
            $this->commit();
            return $r;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function version(): string
    {
        return (string) (oci_server_version($this->resource()) ?: 'unknown');
    }

    public function close(): void
    {
        if ($this->conn !== false) {
            @oci_close($this->conn);
            $this->conn = false;
        }
    }

    /**
     * @param array<string,mixed> $binds
     * @param array<string,array{type:int,length:int}> $skip 不要做標準綁定（由呼叫端自己 bind）
     * @return resource
     */
    private function prepare(string $sql, array $binds, array $skip = [])
    {
        $stmt = oci_parse($this->resource(), $sql);
        if ($stmt === false) {
            $err = oci_error($this->resource());
            throw new \RuntimeException('解析 SQL 失敗: ' . ($err['message'] ?? '') . ' | ' . $sql);
        }
        // 必須以參考綁定，因此維持 $values 陣列
        $this->bindStore = [];
        foreach ($binds as $name => $val) {
            if (isset($skip[$name])) continue;
            $placeholder = ':' . $name;
            if (is_array($val) && array_key_exists('value', $val)) {
                $this->bindStore[$name] = $val['value'];
                $type = $val['type'] ?? SQLT_CHR;
                $length = $val['length'] ?? -1;
                oci_bind_by_name($stmt, $placeholder, $this->bindStore[$name], $length, $type);
            } else {
                $this->bindStore[$name] = $val;
                oci_bind_by_name($stmt, $placeholder, $this->bindStore[$name]);
            }
        }
        // 提升 LOB 抓取
        return $stmt;
    }

    /**
     * 將 OCI-Lob 物件轉字串、欄位名小寫。
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (is_object($v) && $v instanceof \OCILob) {
                $v = $v->load();
            }
            $out[strtolower((string)$k)] = $v;
        }
        return $out;
    }
}
