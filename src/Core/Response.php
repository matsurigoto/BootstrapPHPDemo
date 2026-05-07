<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    public int $status = 200;
    /** @var array<string,string> */
    public array $headers = [];
    public string $body = '';

    public static function html(string $body, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = 'text/html; charset=UTF-8';
        $r->body = $body;
        return $r;
    }

    public static function json($data, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = 'application/json; charset=UTF-8';
        $r->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $r;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = $url;
        return $r;
    }

    public static function text(string $body, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = 'text/plain; charset=UTF-8';
        $r->body = $body;
        return $r;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $this->body;
    }
}
