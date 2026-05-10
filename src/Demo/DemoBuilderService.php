<?php
declare(strict_types=1);

namespace App\Demo;

class DemoBuilderService
{
    public function __construct(private DemoStore $store) {}

    public function save(int $formId, array $payload): array
    {
        return $this->store->transaction(function () use ($formId, $payload) {
            // 1. basic
            if (!empty($payload['basic']) && is_array($payload['basic'])) {
                (new DemoFormRepository($this->store))->updateBasic($formId, $payload['basic']);
            }

            // 2. pages
            $clientPages = $payload['pages'] ?? [];
            $existing = array_values(array_filter($this->store->table('form_pages'), fn($r) => (int)$r['form_id'] === $formId));
            $existingIds = array_map(fn($r) => (int)$r['id'], $existing);
            $kept = [];
            $pageMap = [];
            $pages = $this->store->table('form_pages');
            foreach ($clientPages as $i => $p) {
                $pid = isset($p['id']) && (int)$p['id'] > 0 ? (int)$p['id'] : 0;
                $clientKey = $p['client_id'] ?? ('p_' . $i);
                if ($pid > 0 && in_array($pid, $existingIds, true)) {
                    foreach ($pages as &$row) {
                        if ((int)$row['id'] === $pid) {
                            $row['sort_no'] = (int)($p['sort_no'] ?? $i);
                            $row['title'] = $p['title'] ?? null;
                        }
                    }
                    unset($row);
                } else {
                    $pid = $this->store->nextId('form_pages');
                    $pages[] = ['id' => $pid, 'form_id' => $formId, 'sort_no' => (int)($p['sort_no'] ?? $i), 'title' => $p['title'] ?? null];
                }
                $kept[] = $pid;
                $pageMap[$clientKey] = $pid;
            }
            $toDelete = array_diff($existingIds, $kept);
            if ($toDelete) {
                $pages = array_values(array_filter($pages, fn($r) => !((int)$r['form_id'] === $formId && in_array((int)$r['id'], $toDelete, true))));
            }
            $this->store->setTable('form_pages', $pages);

            // 3. questions
            $clientQs = $payload['questions'] ?? [];
            $existQs = array_values(array_filter($this->store->table('questions'), fn($r) => (int)$r['form_id'] === $formId));
            $existQIds = array_map(fn($r) => (int)$r['id'], $existQs);
            $keptQ = [];
            $qMap = [];
            $qs = $this->store->table('questions');
            foreach ($clientQs as $i => $q) {
                $qid = isset($q['id']) && (int)$q['id'] > 0 ? (int)$q['id'] : 0;
                $clientKey = $q['client_id'] ?? ('q_' . $i);
                $pageId = null;
                if (!empty($q['page_client_id']) && isset($pageMap[$q['page_client_id']])) {
                    $pageId = $pageMap[$q['page_client_id']];
                } elseif (!empty($q['page_id'])) {
                    $pageId = (int)$q['page_id'];
                }
                $type = strtoupper($q['type'] ?? 'TEXT');
                $label = (string)($q['label'] ?? '');
                $help = $q['help_text'] ?? null;
                $req = !empty($q['required']) && $q['required'] !== 'N' ? 'Y' : 'N';
                $configJson = isset($q['config']) ? json_encode($q['config'], JSON_UNESCAPED_UNICODE) : '{}';

                if ($qid > 0 && in_array($qid, $existQIds, true)) {
                    foreach ($qs as &$row) {
                        if ((int)$row['id'] === $qid && (int)$row['form_id'] === $formId) {
                            $row['page_id'] = $pageId;
                            $row['sort_no'] = (int)($q['sort_no'] ?? $i);
                            $row['type'] = $type;
                            $row['label'] = $label;
                            $row['help_text'] = $help;
                            $row['required'] = $req;
                            $row['config_json'] = $configJson;
                        }
                    }
                    unset($row);
                } else {
                    $qid = $this->store->nextId('questions');
                    $qs[] = [
                        'id' => $qid, 'form_id' => $formId, 'page_id' => $pageId,
                        'sort_no' => (int)($q['sort_no'] ?? $i), 'type' => $type, 'label' => $label,
                        'help_text' => $help, 'required' => $req, 'config_json' => $configJson,
                    ];
                }
                $keptQ[] = $qid;
                $qMap[$clientKey] = $qid;
            }
            $toDelQ = array_diff($existQIds, $keptQ);
            if ($toDelQ) {
                $qs = array_values(array_filter($qs, fn($r) => !((int)$r['form_id'] === $formId && in_array((int)$r['id'], $toDelQ, true))));
            }
            $this->store->setTable('questions', $qs);

            // 4. options：先刪該題目的所有 options 再插入
            $opts = $this->store->table('question_options');
            // 移除所有 keptQ 中題目的 options (整個 form 重建 options 簡化)
            $opts = array_values(array_filter($opts, fn($r) => !in_array((int)$r['question_id'], $keptQ, true)));
            // 也移除已刪除題目的 options
            $opts = array_values(array_filter($opts, fn($r) => !in_array((int)$r['question_id'], iterator_to_array((function() use ($toDelQ) { foreach ($toDelQ as $x) yield $x; })()), true)));
            foreach ($clientQs as $i => $q) {
                $clientKey = $q['client_id'] ?? ('q_' . $i);
                $qid = $qMap[$clientKey] ?? null;
                if (!$qid) continue;
                foreach (($q['options'] ?? []) as $j => $opt) {
                    $oid = $this->store->nextId('question_options');
                    $opts[] = [
                        'id' => $oid, 'question_id' => $qid, 'sort_no' => (int)($opt['sort_no'] ?? $j),
                        'label' => (string)($opt['label'] ?? ''), 'opt_value' => $opt['value'] ?? ($opt['opt_value'] ?? null),
                    ];
                }
            }
            $this->store->setTable('question_options', $opts);

            // 5. rules：全刪表單內所有規則並重建
            $rules = $this->store->table('question_rules');
            $rules = array_values(array_filter($rules, fn($r) => !in_array((int)$r['question_id'], array_merge($keptQ, iterator_to_array((function() use ($toDelQ) { foreach ($toDelQ as $x) yield $x; })())), true)));
            foreach ($clientQs as $i => $q) {
                $clientKey = $q['client_id'] ?? ('q_' . $i);
                $qid = $qMap[$clientKey] ?? null;
                if (!$qid) continue;
                foreach (($q['rules'] ?? []) as $r) {
                    $sourceKey = $r['source_client_id'] ?? null;
                    $sourceId = $sourceKey && isset($qMap[$sourceKey]) ? $qMap[$sourceKey] : ($r['source_question_id'] ?? null);
                    if (!$sourceId) continue;
                    $rid = $this->store->nextId('question_rules');
                    $rules[] = [
                        'id' => $rid, 'question_id' => $qid, 'source_question_id' => (int)$sourceId,
                        'operator' => strtoupper($r['operator'] ?? 'EQ'),
                        'compare_value' => $r['compare_value'] ?? null,
                        'action' => strtoupper($r['action'] ?? 'SHOW'),
                    ];
                }
            }
            $this->store->setTable('question_rules', $rules);

            // 6. audiences
            if (array_key_exists('audiences', $payload)) {
                $auds = array_values(array_filter($this->store->table('form_audiences'), fn($r) => (int)$r['form_id'] !== $formId));
                foreach ((array)$payload['audiences'] as $a) {
                    $type = strtoupper($a['principal_type'] ?? '');
                    $val  = trim((string)($a['principal_value'] ?? ''));
                    if (!in_array($type, ['USER','AD_GROUP'], true) || $val === '') continue;
                    $aid = $this->store->nextId('form_audiences');
                    $auds[] = ['id' => $aid, 'form_id' => $formId, 'principal_type' => $type, 'principal_value' => $val];
                }
                $this->store->setTable('form_audiences', $auds);
            }

            return ['form_id' => $formId, 'page_map' => $pageMap, 'q_map' => $qMap];
        });
    }
}
