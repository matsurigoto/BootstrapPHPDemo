<?php
declare(strict_types=1);

namespace App\I18n;

class Translator
{
    /** @var array<string,array<string,string>> */
    private array $cache = [];
    private string $locale;
    private string $fallback;
    private string $langDir;

    public function __construct(string $langDir, string $locale = 'zh-TW', string $fallback = 'zh-TW')
    {
        $this->langDir = $langDir;
        $this->locale = $locale;
        $this->fallback = $fallback;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function t(string $key, array $params = []): string
    {
        $msg = $this->load($this->locale)[$key]
            ?? $this->load($this->fallback)[$key]
            ?? $key;
        if ($params) {
            foreach ($params as $k => $v) {
                $msg = str_replace('{' . $k . '}', (string)$v, $msg);
            }
        }
        return $msg;
    }

    /**
     * @return array<string,string>
     */
    private function load(string $locale): array
    {
        if (isset($this->cache[$locale])) return $this->cache[$locale];
        $file = $this->langDir . '/' . $locale . '.php';
        $arr = is_file($file) ? require $file : [];
        return $this->cache[$locale] = is_array($arr) ? $arr : [];
    }
}
