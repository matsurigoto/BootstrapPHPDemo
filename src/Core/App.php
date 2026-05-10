<?php
declare(strict_types=1);

namespace App\Core;

use App\Auth\LdapAuth;
use App\Db\OciConnection;
use App\I18n\Translator;

class App
{
    public Container $c;
    public Router $router;
    public array $cfg;

    public function __construct(string $rootDir)
    {
        $this->cfg = require $rootDir . '/config/config.php';
        date_default_timezone_set($this->cfg['app']['timezone'] ?? 'UTC');

        if (($this->cfg['app']['env'] ?? 'production') === 'production') {
            ini_set('display_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        } else {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        }

        Session::start($this->cfg['app']['session'] ?? []);

        $this->c = new Container();
        $this->router = new Router();
        $cfg = $this->cfg;
        $rootDir = $rootDir;

        $this->c->set('config', fn() => $cfg);
        $this->c->set('rootDir', fn() => $rootDir);

        $demo = !empty($cfg['app']['demo_mode']);
        if ($demo) {
            $storeFile = $rootDir . '/storage/demo.json';
            $this->c->set('demoStore', fn() => new \App\Demo\DemoStore($storeFile));
            $this->c->set('db',           fn($c) => new \App\Demo\DemoDb($c->get('demoStore')));
            $this->c->set('ldap',         fn($c) => new \App\Demo\DemoLdapAuth($c->get('demoStore')));
            $this->c->set('formRepo',     fn($c) => new \App\Demo\DemoFormRepository($c->get('demoStore')));
            $this->c->set('responseRepo', fn($c) => new \App\Demo\DemoResponseRepository($c->get('demoStore')));
            $this->c->set('builder',      fn($c) => new \App\Demo\DemoBuilderService($c->get('demoStore')));
            $this->c->set('exporter',     fn($c) => new \App\Demo\DemoExporter($c->get('demoStore')));
            $this->c->set('stats',        fn($c) => new \App\Demo\DemoStatsService($c->get('demoStore')));
        } else {
            $this->c->set('db', fn() => new OciConnection($cfg['db']));
            $this->c->set('ldap', fn($c) => new LdapAuth($cfg['ldap'] ?? [], $c->get('db')));
            $this->c->set('formRepo',     fn($c) => new \App\Models\FormRepository($c->get('db')));
            $this->c->set('responseRepo', fn($c) => new \App\Models\ResponseRepository($c->get('db')));
            $this->c->set('builder',      fn($c) => new \App\Services\FormBuilderService($c->get('db')));
            $this->c->set('exporter',     fn($c) => new \App\Services\Exporter($c->get('db')));
            $this->c->set('stats',        fn($c) => new \App\Services\StatsService($c->get('db')));
        }

        $this->c->set('translator', function () use ($rootDir, $cfg) {
            $locale = $_COOKIE['locale']
                ?? $cfg['app']['locale'] ?? 'zh-TW';
            if (!in_array($locale, $cfg['app']['locales'] ?? [], true)) {
                $locale = $cfg['app']['locale'] ?? 'zh-TW';
            }
            return new Translator($rootDir . '/lang', $locale, $cfg['app']['locale'] ?? 'zh-TW');
        });
        $this->c->set('view', function ($c) use ($rootDir, $cfg) {
            return new View($rootDir . '/templates', $cfg['app']['base_url'] ?? '/', $c->get('translator'));
        });
        $this->c->set('validator',    fn() => new \App\Services\Validator());
        $this->c->set('uploader',     fn() => new \App\Services\UploadService($cfg['upload'] ?? []));
    }

    public function run(): void
    {
        $req = Request::capture();

        // 將 base_url 移除前綴
        $base = rtrim($this->cfg['app']['base_url'] ?? '/', '/');
        if ($base !== '' && strpos($req->path, $base) === 0) {
            $req->path = substr($req->path, strlen($base)) ?: '/';
        }

        $match = $this->router->match($req->method, $req->path);
        if ($match === null) {
            (Response::html($this->renderError(404, 'Not Found'), 404))->send();
            return;
        }
        $req->params = $match['params'];
        try {
            // CSRF for POST (排除部分 API endpoint 自行處理)
            if ($req->isPost() && !$this->skipCsrf($req)) {
                $token = $req->post[$this->cfg['security']['csrf_token_name'] ?? '_csrf']
                      ?? $req->header('X-CSRF-Token');
                if (!Csrf::validate(is_string($token) ? $token : null)) {
                    (Response::text('Invalid CSRF token', 419))->send();
                    return;
                }
            }
            $resp = $this->dispatch($match['handler'], $req);
            if ($resp instanceof Response) {
                $resp->send();
            } elseif (is_string($resp)) {
                (Response::html($resp))->send();
            } elseif (is_array($resp) || is_object($resp)) {
                (Response::json($resp))->send();
            }
        } catch (\Throwable $e) {
            $debug = !empty($this->cfg['app']['debug']);
            $body = $debug
                ? '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>'
                : $this->renderError(500, 'Internal Server Error');
            (Response::html($body, 500))->send();
        }
    }

    private function skipCsrf(Request $req): bool
    {
        // JSON API 仍要求 X-CSRF-Token；公開填答頁不豁免（會帶 _csrf hidden）
        return false;
    }

    /**
     * @param mixed $handler
     */
    private function dispatch($handler, Request $req)
    {
        if (is_callable($handler)) {
            return $handler($req, $this->c);
        }
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class($this->c);
            return $instance->$method($req);
        }
        throw new \RuntimeException('Invalid route handler');
    }

    private function renderError(int $status, string $msg): string
    {
        try {
            return $this->c->get('view')->render('error', ['status' => $status, 'msg' => $msg]);
        } catch (\Throwable) {
            return "<h1>{$status}</h1><p>" . htmlspecialchars($msg) . '</p>';
        }
    }
}
