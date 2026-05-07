<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Db\OciConnection;

class HomeController extends Controller
{
    public function index(Request $r): Response
    {
        if (\App\Auth\AuthGuard::check()) {
            return $this->redirect('/forms');
        }
        return $this->redirect('/login');
    }

    public function health(Request $r): Response
    {
        try {
            /** @var OciConnection $db */
            $db = $this->c->get('db');
            $version = $db->version();
            return Response::json(['status' => 'ok', 'oracle' => $version]);
        } catch (\Throwable $e) {
            return Response::json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function setLocale(Request $r): Response
    {
        $locale = (string)$r->input('locale', 'zh-TW');
        $allowed = $this->cfg['app']['locales'] ?? ['zh-TW'];
        if (in_array($locale, $allowed, true)) {
            setcookie('locale', $locale, [
                'expires' => time() + 86400 * 365,
                'path'    => '/',
                'samesite'=> 'Lax',
            ]);
        }
        $back = $r->server['HTTP_REFERER'] ?? '/forms';
        return Response::redirect($back);
    }
}
