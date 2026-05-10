<?php
declare(strict_types=1);

namespace App\Demo;

/**
 * Demo 版 FormRepository — 介面與 App\Models\FormRepository 對齊。
 */
class DemoFormRepository
{
    public function __construct(private DemoStore $store) {}

    public function paginate(?string $q, ?string $status, string $sort, string $dir, int $page, int $perPage, ?int $createdBy = null): array
    {
        $rows = $this->store->table('forms');
        if ($q !== null && $q !== '') {
            $needle = mb_strtolower($q);
            $rows = array_values(array_filter($rows, fn($r) => mb_strpos(mb_strtolower((string)$r['title']), $needle) !== false));
        }
        if ($status !== null && $status !== '') {
            $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $status));
        }
        if ($createdBy !== null) {
            $rows = array_values(array_filter($rows, fn($r) => (int)$r['created_by'] === $createdBy));
        }

        // 加上 creator_name 與 responses_count
        $users = [];
        foreach ($this->store->table('users') as $u) $users[(int)$u['id']] = $u;
        $allResp = $this->store->table('responses');
        $countByForm = [];
        foreach ($allResp as $r) {
            if (($r['status'] ?? '') === 'SUBMITTED') {
                $countByForm[(int)$r['form_id']] = (int)($countByForm[(int)$r['form_id']] ?? 0) + 1;
            }
        }
        foreach ($rows as &$r) {
            $r['creator_name'] = $users[(int)$r['created_by']]['display_name'] ?? null;
            $r['responses_count'] = $countByForm[(int)$r['id']] ?? 0;
        }
        unset($r);

        $allowSort = ['title', 'status', 'created_at', 'updated_at', 'start_at', 'end_at', 'responses' => 'responses_count'];
        $sortKey = is_string($allowSort[$sort] ?? null) ? $allowSort[$sort] : (in_array($sort, $allowSort, true) ? $sort : 'updated_at');
        if (!in_array($sortKey, ['title','status','created_at','updated_at','start_at','end_at','responses_count'], true)) {
            $sortKey = 'updated_at';
        }
        $dirN = strtoupper($dir) === 'ASC' ? 1 : -1;
        usort($rows, function ($a, $b) use ($sortKey, $dirN) {
            $av = $a[$sortKey] ?? null; $bv = $b[$sortKey] ?? null;
            if ($av == $bv) return ((int)$a['id'] - (int)$b['id']) * $dirN;
            return (($av < $bv) ? -1 : 1) * $dirN;
        });

        $total = count($rows);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = array_slice($rows, $offset, $perPage);
        return ['rows' => $rows, 'total' => $total];
    }

    public function find(int $id): ?array
    {
        foreach ($this->store->table('forms') as $r) {
            if ((int)$r['id'] === $id) {
                $users = [];
                foreach ($this->store->table('users') as $u) $users[(int)$u['id']] = $u;
                $r['creator_name'] = $users[(int)$r['created_by']]['display_name'] ?? null;
                return $r;
            }
        }
        return null;
    }

    public function create(int $userId, string $title, ?string $description = null): int
    {
        $id = $this->store->nextId('forms');
        $now = date('Y-m-d H:i:s');
        $rows = $this->store->table('forms');
        $rows[] = [
            'id' => $id, 'title' => $title, 'description' => $description ?? '',
            'status' => 'DRAFT', 'audience_mode' => 'PUBLIC', 'require_login' => 'N',
            'allow_multi' => 'N', 'locale_default' => 'zh-TW', 'start_at' => null, 'end_at' => null,
            'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now, 'published_at' => null,
        ];
        $this->store->setTable('forms', $rows);
        $this->store->save();
        return $id;
    }

    public function updateBasic(int $id, array $data): void
    {
        $rows = $this->store->table('forms');
        foreach ($rows as &$r) {
            if ((int)$r['id'] !== $id) continue;
            foreach (['title','status','audience_mode','require_login','allow_multi','locale_default','description'] as $k) {
                if (array_key_exists($k, $data)) $r[$k] = $data[$k];
            }
            foreach (['start_at','end_at'] as $k) {
                if (array_key_exists($k, $data)) {
                    $r[$k] = ($data[$k] === '' || $data[$k] === null) ? null : str_replace('T', ' ', $data[$k]) . ':00';
                }
            }
            $r['updated_at'] = date('Y-m-d H:i:s');
        }
        unset($r);
        $this->store->setTable('forms', $rows);
        $this->store->save();
    }

    public function updateDescription(int $id, string $desc): void
    {
        $this->updateBasic($id, ['description' => $desc]);
    }

    public function publish(int $id): void
    {
        $this->updateBasic($id, ['status' => 'PUBLISHED']);
        $rows = $this->store->table('forms');
        foreach ($rows as &$r) if ((int)$r['id'] === $id) $r['published_at'] = date('Y-m-d H:i:s');
        unset($r);
        $this->store->setTable('forms', $rows);
        $this->store->save();
    }

    public function unpublish(int $id): void { $this->updateBasic($id, ['status' => 'DRAFT']); }
    public function close(int $id): void     { $this->updateBasic($id, ['status' => 'CLOSED']); }

    public function delete(int $id): void
    {
        // cascade
        $this->store->setTable('forms', array_values(array_filter($this->store->table('forms'), fn($r) => (int)$r['id'] !== $id)));
        $this->store->setTable('form_pages', array_values(array_filter($this->store->table('form_pages'), fn($r) => (int)$r['form_id'] !== $id)));
        $qIds = [];
        $kept = [];
        foreach ($this->store->table('questions') as $r) {
            if ((int)$r['form_id'] === $id) $qIds[] = (int)$r['id']; else $kept[] = $r;
        }
        $this->store->setTable('questions', $kept);
        $this->store->setTable('question_options', array_values(array_filter($this->store->table('question_options'), fn($r) => !in_array((int)$r['question_id'], $qIds, true))));
        $this->store->setTable('question_rules', array_values(array_filter($this->store->table('question_rules'), fn($r) => !in_array((int)$r['question_id'], $qIds, true))));
        $this->store->setTable('form_audiences', array_values(array_filter($this->store->table('form_audiences'), fn($r) => (int)$r['form_id'] !== $id)));
        // responses cascade
        $rIds = [];
        $kept = [];
        foreach ($this->store->table('responses') as $r) {
            if ((int)$r['form_id'] === $id) $rIds[] = (int)$r['id']; else $kept[] = $r;
        }
        $this->store->setTable('responses', $kept);
        $aIds = [];
        $kept = [];
        foreach ($this->store->table('answers') as $r) {
            if (in_array((int)$r['response_id'], $rIds, true)) $aIds[] = (int)$r['id']; else $kept[] = $r;
        }
        $this->store->setTable('answers', $kept);
        $this->store->setTable('answer_options', array_values(array_filter($this->store->table('answer_options'), fn($r) => !in_array((int)$r['answer_id'], $aIds, true))));
        $this->store->save();
    }

    public function loadFull(int $id): ?array
    {
        $form = $this->find($id);
        if (!$form) return null;
        $form['pages']     = array_values(array_filter($this->store->table('form_pages'), fn($r) => (int)$r['form_id'] === $id));
        usort($form['pages'], fn($a, $b) => ((int)$a['sort_no'] - (int)$b['sort_no']) ?: ((int)$a['id'] - (int)$b['id']));
        $form['questions'] = array_values(array_filter($this->store->table('questions'), fn($r) => (int)$r['form_id'] === $id));
        usort($form['questions'], fn($a, $b) => ((int)$a['sort_no'] - (int)$b['sort_no']) ?: ((int)$a['id'] - (int)$b['id']));
        $qIds = array_map(fn($q) => (int)$q['id'], $form['questions']);
        $form['options']   = array_values(array_filter($this->store->table('question_options'), fn($r) => in_array((int)$r['question_id'], $qIds, true)));
        usort($form['options'], fn($a, $b) => ((int)$a['question_id'] - (int)$b['question_id']) ?: ((int)$a['sort_no'] - (int)$b['sort_no']));
        $form['rules']     = array_values(array_filter($this->store->table('question_rules'), fn($r) => in_array((int)$r['question_id'], $qIds, true)));
        $form['audiences'] = array_values(array_filter($this->store->table('form_audiences'), fn($r) => (int)$r['form_id'] === $id));
        return $form;
    }

    public function duplicate(int $id, int $userId): int
    {
        return $this->store->transaction(function () use ($id, $userId) {
            $orig = $this->loadFull($id);
            if (!$orig) throw new \RuntimeException('Form not found');
            $newId = $this->create($userId, $orig['title'] . ' (Copy)', (string)($orig['description'] ?? ''));
            $this->updateBasic($newId, [
                'audience_mode' => $orig['audience_mode'],
                'require_login' => $orig['require_login'],
                'allow_multi'   => $orig['allow_multi'],
                'locale_default'=> $orig['locale_default'],
                'status'        => 'DRAFT',
            ]);

            $pageMap = [];
            $pages = $this->store->table('form_pages');
            foreach ($orig['pages'] as $p) {
                $pid = $this->store->nextId('form_pages');
                $pages[] = ['id' => $pid, 'form_id' => $newId, 'sort_no' => (int)$p['sort_no'], 'title' => $p['title']];
                $pageMap[(int)$p['id']] = $pid;
            }
            $this->store->setTable('form_pages', $pages);

            $qMap = [];
            $qs = $this->store->table('questions');
            foreach ($orig['questions'] as $q) {
                $qid = $this->store->nextId('questions');
                $qs[] = [
                    'id' => $qid, 'form_id' => $newId,
                    'page_id' => $q['page_id'] !== null ? ($pageMap[(int)$q['page_id']] ?? null) : null,
                    'sort_no' => (int)$q['sort_no'], 'type' => $q['type'], 'label' => $q['label'],
                    'help_text' => $q['help_text'], 'required' => $q['required'],
                    'config_json' => $q['config_json'],
                ];
                $qMap[(int)$q['id']] = $qid;
            }
            $this->store->setTable('questions', $qs);

            $optMap = [];
            $os = $this->store->table('question_options');
            foreach ($orig['options'] as $o) {
                $newQ = $qMap[(int)$o['question_id']] ?? null;
                if (!$newQ) continue;
                $oid = $this->store->nextId('question_options');
                $os[] = ['id' => $oid, 'question_id' => $newQ, 'sort_no' => (int)$o['sort_no'],
                         'label' => $o['label'], 'opt_value' => $o['opt_value']];
                $optMap[(int)$o['id']] = $oid;
            }
            $this->store->setTable('question_options', $os);

            $rs = $this->store->table('question_rules');
            foreach ($orig['rules'] as $r) {
                if (!isset($qMap[(int)$r['question_id']]) || !isset($qMap[(int)$r['source_question_id']])) continue;
                $rid = $this->store->nextId('question_rules');
                $rs[] = ['id' => $rid, 'question_id' => $qMap[(int)$r['question_id']],
                         'source_question_id' => $qMap[(int)$r['source_question_id']],
                         'operator' => $r['operator'], 'compare_value' => $r['compare_value'],
                         'action' => $r['action']];
            }
            $this->store->setTable('question_rules', $rs);

            $audRows = $this->store->table('form_audiences');
            foreach ($orig['audiences'] as $a) {
                $aid = $this->store->nextId('form_audiences');
                $audRows[] = ['id' => $aid, 'form_id' => $newId, 'principal_type' => $a['principal_type'], 'principal_value' => $a['principal_value']];
            }
            $this->store->setTable('form_audiences', $audRows);
            return $newId;
        });
    }
}
