<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,params:array<int,string>,handler:mixed}> */
    private array $routes = [];

    public function get(string $pattern, $handler): void    { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, $handler): void   { $this->add('POST', $pattern, $handler); }
    public function put(string $pattern, $handler): void    { $this->add('PUT', $pattern, $handler); }
    public function delete(string $pattern, $handler): void { $this->add('DELETE', $pattern, $handler); }
    public function any(string $pattern, $handler): void    { $this->add('*', $pattern, $handler); }

    private function add(string $method, string $pattern, $handler): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_]\w*)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $regex = '#^' . $regex . '$#';
        $this->routes[] = compact('method', 'pattern', 'regex', 'params', 'handler');
    }

    /**
     * @return array{handler:mixed,params:array<string,string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $r) {
            if ($r['method'] !== '*' && $r['method'] !== $method) continue;
            if (preg_match($r['regex'], $path, $m)) {
                array_shift($m);
                $params = [];
                foreach ($r['params'] as $i => $name) {
                    $params[$name] = $m[$i] ?? '';
                }
                return ['handler' => $r['handler'], 'params' => $params];
            }
        }
        return null;
    }
}
