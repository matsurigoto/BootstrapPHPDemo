<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\LdapAuth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Db\OciConnection;

class AuthController extends Controller
{
    public function showLogin(Request $r): Response
    {
        if (\App\Auth\AuthGuard::check()) return $this->redirect('/forms');
        return $this->render('auth/login', [
            'pageTitle' => $this->c->get('translator')->t('login.title'),
        ]);
    }

    public function doLogin(Request $r): Response
    {
        $username = trim((string)$r->input('username', ''));
        $password = (string)$r->input('password', '');
        $ip = $r->ip();

        // 鎖定檢查
        if ($this->isLocked($username, $ip)) {
            Session::flash('error', $this->c->get('translator')->t('login.locked'));
            return $this->redirect('/login');
        }

        /** @var LdapAuth $ldap */
        $ldap = $this->c->get('ldap');
        try {
            $user = $ldap->authenticate($username, $password);
        } catch (\Throwable $e) {
            $user = null;
        }
        $this->logAttempt($username, $ip, $user !== null);

        if ($user === null) {
            Session::flash('error', $this->c->get('translator')->t('login.error'));
            return $this->redirect('/login');
        }
        Session::set('user', $user);
        // regenerate session id
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $back = Session::get('redirect_after_login') ?: '/forms';
        Session::forget('redirect_after_login');
        return Response::redirect($back);
    }

    public function logout(Request $r): Response
    {
        Session::destroy();
        return $this->redirect('/login');
    }

    private function isLocked(string $username, string $ip): bool
    {
        $cfg = $this->cfg['security'];
        $max = (int)($cfg['login_max_attempts'] ?? 5);
        $win = (int)($cfg['login_lockout_seconds'] ?? 600);
        if ($username === '') return false;
        try {
            /** @var OciConnection $db */
            $db = $this->c->get('db');
            $cnt = (int) $db->scalar(
                "SELECT COUNT(*) FROM LOGIN_ATTEMPTS
                 WHERE USERNAME = :u AND SUCCESS = 'N'
                   AND ATTEMPTED_AT > SYSTIMESTAMP - NUMTODSINTERVAL(:w, 'SECOND')",
                ['u' => $username, 'w' => $win]
            );
            return $cnt >= $max;
        } catch (\Throwable) {
            return false;
        }
    }

    private function logAttempt(string $username, string $ip, bool $ok): void
    {
        try {
            /** @var OciConnection $db */
            $db = $this->c->get('db');
            $db->execute(
                'INSERT INTO LOGIN_ATTEMPTS (USERNAME, IP_ADDR, SUCCESS) VALUES (:u, :ip, :s)',
                ['u' => $username, 'ip' => $ip, 's' => $ok ? 'Y' : 'N']
            );
        } catch (\Throwable) {
            // 紀錄失敗不影響登入流程
        }
    }
}
