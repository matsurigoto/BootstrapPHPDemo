<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    public string $method;
    public string $path;
    /** @var array<string,string> */
    public array $query;
    /** @var array<string,mixed> */
    public array $post;
    /** @var array<string,mixed> */
    public array $files;
    /** @var array<string,string> */
    public array $cookies;
    /** @var array<string,string> */
    public array $server;
    /** @var array<string,string> */
    public array $params = []; // route params

    public static function capture(): self
    {
        $r = new self();
        $r->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $r->path = '/' . trim($path, '/');
        if ($r->path === '/') {
            $r->path = '/';
        }
        $r->query   = $_GET ?? [];
        $r->post    = $_POST ?? [];
        $r->files   = $_FILES ?? [];
        $r->cookies = $_COOKIE ?? [];
        $r->server  = $_SERVER ?? [];
        return $r;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function isJson(): bool
    {
        $ct = $this->header('Content-Type') ?? '';
        return stripos($ct, 'application/json') !== false;
    }

    /**
     * @return array<string,mixed>
     */
    public function json(): array
    {
        $body = file_get_contents('php://input') ?: '';
        if ($body === '') return [];
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }
}
