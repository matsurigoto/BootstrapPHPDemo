<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthGuard;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\FormRepository;
use App\Services\FormBuilderService;

class FormApiController extends Controller
{
    public function getForm(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->loadFull($id);
        if (!$form) return $this->json(['error' => 'not_found'], 404);
        if (AuthGuard::isAdmin() === false && (int)$form['created_by'] !== (int)AuthGuard::id()) {
            return $this->json(['error' => 'forbidden'], 403);
        }
        // 將 client 視角整理：rules 直接附在每題裡
        $rulesByQ = [];
        foreach ($form['rules'] as $rule) {
            $rulesByQ[(int)$rule['question_id']][] = $rule;
        }
        $optsByQ = [];
        foreach ($form['options'] as $o) {
            $optsByQ[(int)$o['question_id']][] = $o;
        }
        $questions = [];
        foreach ($form['questions'] as $q) {
            $qid = (int)$q['id'];
            $q['options'] = $optsByQ[$qid] ?? [];
            $q['rules']   = $rulesByQ[$qid] ?? [];
            if (!empty($q['config_json'])) {
                $q['config'] = json_decode((string)$q['config_json'], true) ?: new \stdClass();
            } else {
                $q['config'] = new \stdClass();
            }
            $questions[] = $q;
        }
        unset($form['rules'], $form['options']);
        $form['questions'] = $questions;
        $form['_csrf'] = Csrf::token();
        return $this->json($form);
    }

    public function saveForm(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        // CSRF 已在 App 層驗證 (POST)
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($id);
        if (!$form) return $this->json(['error' => 'not_found'], 404);
        if (AuthGuard::isAdmin() === false && (int)$form['created_by'] !== (int)AuthGuard::id()) {
            return $this->json(['error' => 'forbidden'], 403);
        }
        $payload = $r->json();
        if (!$payload) return $this->json(['error' => 'invalid_payload'], 400);

        try {
            /** @var FormBuilderService $svc */
            $svc = $this->c->get('builder');
            $result = $svc->save($id, $payload);
            return $this->json(['ok' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
        }
    }
}
