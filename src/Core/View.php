<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    public function __construct(
        private string $templateDir,
        private string $baseUrl = '/',
        private ?\App\I18n\Translator $t = null
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->renderRaw($template, $data);
        if ($layout) {
            $data['_content'] = $content;
            return $this->renderRaw($layout, $data);
        }
        return $content;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function renderRaw(string $template, array $data = []): string
    {
        $file = $this->templateDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        $view = $this;
        $baseUrl = $this->baseUrl;
        $t = $this->t;
        // 提供短函式
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $url = fn(string $p) => rtrim($baseUrl, '/') . '/' . ltrim($p, '/');
        $tr = function (string $k, array $p = []) use ($t) {
            return $t ? $t->t($k, $p) : $k;
        };
        $csrfField = static fn() => Csrf::field();
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }
        return (string) ob_get_clean();
    }
}
