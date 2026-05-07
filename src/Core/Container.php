<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 極簡服務容器：以 closure factory 註冊單例。
 */
class Container
{
    /** @var array<string,callable> */
    private array $factories = [];
    /** @var array<string,mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    /**
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service not registered: {$id}");
        }
        return $this->instances[$id] = ($this->factories[$id])($this);
    }
}
