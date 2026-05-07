<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

abstract class Controller
{
    protected Container $c;
    protected View $view;
    protected array $cfg;

    public function __construct(Container $c)
    {
        $this->c = $c;
        $this->view = $c->get('view');
        $this->cfg = $c->get('config');
    }

    protected function render(string $tpl, array $data = [], ?string $layout = 'layout'): Response
    {
        $data['_user'] = \App\Auth\AuthGuard::user();
        $data['_locale'] = $this->c->get('translator')->locale();
        $data['_locales'] = $this->cfg['app']['locales'] ?? ['zh-TW'];
        $data['_base'] = rtrim($this->cfg['app']['base_url'] ?? '/', '/');
        $data['_app_name'] = $this->cfg['app']['name'] ?? 'FormHub';
        $data['_flash_success'] = \App\Core\Session::flash('success');
        $data['_flash_error']   = \App\Core\Session::flash('error');
        return Response::html($this->view->render($tpl, $data, $layout));
    }

    protected function json($data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url): Response
    {
        $base = rtrim($this->cfg['app']['base_url'] ?? '/', '/');
        if ($url !== '' && $url[0] === '/') $url = $base . $url;
        return Response::redirect($url);
    }

    protected function requireLogin(): ?Response
    {
        if (!\App\Auth\AuthGuard::check()) {
            \App\Core\Session::set('redirect_after_login', $_SERVER['REQUEST_URI'] ?? '/');
            return $this->redirect('/login');
        }
        return null;
    }

    protected function requireAdmin(): ?Response
    {
        if (($r = $this->requireLogin()) !== null) return $r;
        if (!\App\Auth\AuthGuard::isAdmin()) {
            return Response::text('Forbidden', 403);
        }
        return null;
    }
}
