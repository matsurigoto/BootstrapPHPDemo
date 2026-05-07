<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthGuard;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Db\OciConnection;
use App\Models\FormRepository;
use App\Models\ResponseRepository;
use App\Services\Validator;
use App\Services\UploadService;

class FillController extends Controller
{
    public function show(Request $r): Response
    {
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->loadFull($id);
        if (!$form) return Response::text('Not Found', 404);

        $err = $this->checkAvailability($form);
        if ($err !== null) {
            return $this->render('forms/closed', ['pageTitle' => '無法填答', 'form' => $form, 'reason' => $err]);
        }
        return $this->render('forms/fill', [
            'pageTitle' => $form['title'],
            'form'      => $form,
            'isPreview' => false,
        ]);
    }

    public function submit(Request $r): Response
    {
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->loadFull($id);
        if (!$form) return Response::text('Not Found', 404);
        $err = $this->checkAvailability($form);
        if ($err !== null) {
            return $this->render('forms/closed', ['pageTitle' => '無法填答', 'form' => $form, 'reason' => $err]);
        }
        $isPreview = !empty($r->post['_preview']);
        $userId = AuthGuard::id();

        // 重複填答檢查
        /** @var ResponseRepository $respRepo */
        $respRepo = $this->c->get('responseRepo');
        $allowMulti = ($form['allow_multi'] ?? 'N') === 'Y';
        $anonToken = null;
        if (!$allowMulti && !$isPreview) {
            if ($userId !== null) {
                if ($respRepo->countByUser($id, $userId) > 0) {
                    return $this->render('forms/closed', ['pageTitle' => '無法填答', 'form' => $form, 'reason' => $this->c->get('translator')->t('form.fill.duplicated')]);
                }
            } else {
                $anonToken = $r->cookies['anon_' . $id] ?? null;
                if ($anonToken && $respRepo->countByAnonToken($id, $anonToken) > 0) {
                    return $this->render('forms/closed', ['pageTitle' => '無法填答', 'form' => $form, 'reason' => $this->c->get('translator')->t('form.fill.duplicated')]);
                }
            }
        }

        // 收集答案
        $answers = $r->post['a'] ?? [];
        // 轉成 int key
        $byQ = [];
        if (is_array($answers)) {
            foreach ($answers as $k => $v) $byQ[(int)$k] = $v;
        }
        $files = [];
        if (!empty($r->files['a'])) {
            $f = $r->files['a'];
            // PHP files for a[qid]: arrays normalized
            foreach (($f['name'] ?? []) as $qid => $name) {
                if (!is_string($name)) continue;
                $files[(int)$qid] = [
                    'name'     => $name,
                    'type'     => $f['type'][$qid] ?? '',
                    'tmp_name' => $f['tmp_name'][$qid] ?? '',
                    'size'     => $f['size'][$qid] ?? 0,
                    'error'    => $f['error'][$qid] ?? UPLOAD_ERR_NO_FILE,
                ];
            }
        }

        $optsByQ = [];
        foreach ($form['options'] as $o) $optsByQ[(int)$o['question_id']][] = $o;

        /** @var Validator $validator */
        $validator = $this->c->get('validator');
        $vr = $validator->validate($form['questions'], $form['options'], $form['rules'], $byQ, $files);

        if ($vr['errors']) {
            // 簡化：以 flash 回填錯誤
            Session::flash('errors', $vr['errors']);
            Session::flash('error', $this->c->get('translator')->t('common.fail'));
            return $this->redirect('/f/' . $id);
        }

        if ($isPreview) {
            // 預覽不寫入
            return $this->render('forms/thanks', ['pageTitle' => '預覽', 'form' => $form, 'isPreview' => true]);
        }

        // 寫入
        $token = $anonToken ?: bin2hex(random_bytes(8));
        /** @var OciConnection $db */
        $db = $this->c->get('db');
        $responseId = $db->transaction(function () use ($respRepo, $id, $userId, $r, $token, $vr, $form) {
            $rid = $respRepo->createResponse($id, $userId, $r->ip(), $r->userAgent(), $token);
            /** @var UploadService $uploader */
            $uploader = $this->c->get('uploader');
            $resolver = function (int $qid, array $f) use ($uploader, $id, $rid) {
                $stored = $uploader->store($f, $id, $rid);
                return [$stored['path'], $stored['name']];
            };
            $respRepo->writeAnswers($rid, $vr['normalized'], $resolver);
            return $rid;
        });

        if (!$userId) {
            setcookie('anon_' . $id, $token, [
                'expires' => time() + 86400 * 30,
                'path'    => '/',
                'samesite'=> 'Lax',
                'httponly'=> true,
            ]);
        }

        return $this->render('forms/thanks', ['pageTitle' => '感謝', 'form' => $form, 'isPreview' => false]);
    }

    /**
     * @return string|null  null=可填寫；否則回錯誤訊息
     */
    private function checkAvailability(array $form): ?string
    {
        $t = $this->c->get('translator');
        if (($form['status'] ?? '') !== 'PUBLISHED') {
            return $t->t('form.fill.closed');
        }
        $now = time();
        if (!empty($form['start_at']) && strtotime((string)$form['start_at']) > $now) {
            return $t->t('form.fill.closed');
        }
        if (!empty($form['end_at']) && strtotime((string)$form['end_at']) < $now) {
            return $t->t('form.fill.closed');
        }
        // require_login
        if (($form['require_login'] ?? 'N') === 'Y' && !AuthGuard::check()) {
            \App\Core\Session::set('redirect_after_login', '/f/' . $form['id']);
            return null; // 由 controller redirect
        }
        // audience
        $mode = $form['audience_mode'] ?? 'PUBLIC';
        if ($mode === 'PUBLIC') return null;
        if (!AuthGuard::check()) {
            \App\Core\Session::set('redirect_after_login', '/f/' . $form['id']);
            return $t->t('form.fill.no_perm');
        }
        $user = AuthGuard::user();
        $allowed = false;
        if ($mode === 'USER') {
            foreach ($form['audiences'] as $a) {
                if ($a['principal_type'] === 'USER' && strcasecmp($a['principal_value'], $user['username']) === 0) {
                    $allowed = true; break;
                }
            }
        } elseif ($mode === 'GROUP') {
            $groups = $user['groups'] ?? [];
            foreach ($form['audiences'] as $a) {
                if ($a['principal_type'] !== 'AD_GROUP') continue;
                foreach ($groups as $g) {
                    if (stripos($g, (string)$a['principal_value']) !== false) {
                        $allowed = true; break 2;
                    }
                }
            }
        }
        return $allowed ? null : $t->t('form.fill.no_perm');
    }
}
