<?php
declare(strict_types=1);

namespace App\Services;

use App\Db\OciConnection;

/**
 * 處理表單建構器送來的整體 schema (pages/questions/options/rules/audiences)。
 * 採用 diff-by-id 策略：客端可帶舊 ID（保留）或不帶（新增）；伺服器端最終以
 * 「保留客端送來的清單，刪除沒在清單裡的舊資料」原則 sync。
 */
class FormBuilderService
{
    public function __construct(private OciConnection $db) {}

    /**
     * @param array<string,mixed> $payload
     *   pages: [{id?, sort_no, title}]
     *   questions: [{id?, page_id?(client side index), sort_no, type, label, help_text, required, config, options:[{id?, sort_no, label, value}], rules:[{source_qid, operator, compare_value, action}]}]
     *   audiences: [{principal_type, principal_value}]
     *   basic: {title, description, status, audience_mode, require_login, allow_multi, start_at, end_at, locale_default}
     */
    public function save(int $formId, array $payload): array
    {
        return $this->db->transaction(function ($db) use ($formId, $payload) {
            // 1. basic
            if (!empty($payload['basic']) && is_array($payload['basic'])) {
                (new \App\Models\FormRepository($db))->updateBasic($formId, $payload['basic']);
            }

            // 2. pages: 建立 keyed map（依 client_id）
            $clientPages = $payload['pages'] ?? [];
            $existingPages = $db->fetchAll('SELECT ID FROM FORM_PAGES WHERE FORM_ID = :f', ['f' => $formId]);
            $existingPageIds = array_map(fn($r) => (int)$r['id'], $existingPages);
            $keptPageIds = [];
            $pageIdMap = []; // client_id → real id
            foreach ($clientPages as $i => $p) {
                $pid = isset($p['id']) && (int)$p['id'] > 0 ? (int)$p['id'] : 0;
                $clientKey = $p['client_id'] ?? ('p_' . $i);
                if ($pid > 0 && in_array($pid, $existingPageIds, true)) {
                    $db->execute(
                        'UPDATE FORM_PAGES SET SORT_NO = :s, TITLE = :t WHERE ID = :id AND FORM_ID = :f',
                        ['s' => (int)($p['sort_no'] ?? $i), 't' => $p['title'] ?? null, 'id' => $pid, 'f' => $formId]
                    );
                } else {
                    $pid = $db->insertReturningId(
                        'INSERT INTO FORM_PAGES (FORM_ID, SORT_NO, TITLE) VALUES (:f, :s, :t) RETURNING ID INTO :new_id',
                        ['f' => $formId, 's' => (int)($p['sort_no'] ?? $i), 't' => $p['title'] ?? null]
                    );
                }
                $keptPageIds[] = $pid;
                $pageIdMap[$clientKey] = $pid;
            }
            $toDelete = array_diff($existingPageIds, $keptPageIds);
            foreach ($toDelete as $pid) {
                $db->execute('DELETE FROM FORM_PAGES WHERE ID = :id', ['id' => $pid]);
            }

            // 3. questions
            $clientQs = $payload['questions'] ?? [];
            $existingQs = $db->fetchAll('SELECT ID FROM QUESTIONS WHERE FORM_ID = :f', ['f' => $formId]);
            $existingQIds = array_map(fn($r) => (int)$r['id'], $existingQs);
            $keptQIds = [];
            $qIdMap = []; // client_id → real id
            foreach ($clientQs as $i => $q) {
                $qid = isset($q['id']) && (int)$q['id'] > 0 ? (int)$q['id'] : 0;
                $clientKey = $q['client_id'] ?? ('q_' . $i);
                $pageId = null;
                if (!empty($q['page_client_id']) && isset($pageIdMap[$q['page_client_id']])) {
                    $pageId = $pageIdMap[$q['page_client_id']];
                } elseif (!empty($q['page_id'])) {
                    $pageId = (int)$q['page_id'];
                }
                $configJson = isset($q['config']) ? json_encode($q['config'], JSON_UNESCAPED_UNICODE) : null;
                $type     = strtoupper($q['type'] ?? 'TEXT');
                $label    = (string)($q['label'] ?? '');
                $helpText = $q['help_text'] ?? null;
                $required = !empty($q['required']) ? 'Y' : 'N';

                if ($qid > 0 && in_array($qid, $existingQIds, true)) {
                    $db->execute(
                        'UPDATE QUESTIONS SET PAGE_ID = :pg, SORT_NO = :s, TYPE = :ty, LABEL = :lb,
                                              HELP_TEXT = :ht, REQUIRED = :rq, CONFIG_JSON = :cfg
                         WHERE ID = :id AND FORM_ID = :f',
                        [
                            'pg' => $pageId, 's' => (int)($q['sort_no'] ?? $i),
                            'ty' => $type, 'lb' => $label, 'ht' => $helpText, 'rq' => $required,
                            'cfg' => ['value' => (string)$configJson, 'type' => SQLT_CHR, 'length' => -1],
                            'id' => $qid, 'f' => $formId,
                        ]
                    );
                } else {
                    $qid = $db->insertReturningId(
                        'INSERT INTO QUESTIONS (FORM_ID, PAGE_ID, SORT_NO, TYPE, LABEL, HELP_TEXT, REQUIRED, CONFIG_JSON)
                         VALUES (:f, :pg, :s, :ty, :lb, :ht, :rq, :cfg)
                         RETURNING ID INTO :new_id',
                        [
                            'f' => $formId, 'pg' => $pageId, 's' => (int)($q['sort_no'] ?? $i),
                            'ty' => $type, 'lb' => $label, 'ht' => $helpText, 'rq' => $required,
                            'cfg' => ['value' => (string)$configJson, 'type' => SQLT_CHR, 'length' => -1],
                        ]
                    );
                }
                $keptQIds[] = $qid;
                $qIdMap[$clientKey] = $qid;
            }
            $toDelQ = array_diff($existingQIds, $keptQIds);
            foreach ($toDelQ as $qid) {
                $db->execute('DELETE FROM QUESTIONS WHERE ID = :id', ['id' => $qid]);
            }

            // 4. options & rules：先全刪該 question 再插入（簡化）
            foreach ($clientQs as $i => $q) {
                $clientKey = $q['client_id'] ?? ('q_' . $i);
                $qid = $qIdMap[$clientKey] ?? null;
                if (!$qid) continue;
                $db->execute('DELETE FROM QUESTION_OPTIONS WHERE QUESTION_ID = :q', ['q' => $qid]);
                foreach (($q['options'] ?? []) as $j => $opt) {
                    $db->execute(
                        'INSERT INTO QUESTION_OPTIONS (QUESTION_ID, SORT_NO, LABEL, OPT_VALUE)
                         VALUES (:q, :s, :l, :v)',
                        [
                            'q' => $qid, 's' => (int)($opt['sort_no'] ?? $j),
                            'l' => (string)($opt['label'] ?? ''), 'v' => $opt['value'] ?? null,
                        ]
                    );
                }
            }

            // rules：全刪表單內所有規則再依 client_id 對映重建
            $db->execute(
                'DELETE FROM QUESTION_RULES WHERE QUESTION_ID IN (SELECT ID FROM QUESTIONS WHERE FORM_ID = :f)',
                ['f' => $formId]
            );
            foreach ($clientQs as $i => $q) {
                $clientKey = $q['client_id'] ?? ('q_' . $i);
                $qid = $qIdMap[$clientKey] ?? null;
                if (!$qid) continue;
                foreach (($q['rules'] ?? []) as $r) {
                    $sourceKey = $r['source_client_id'] ?? null;
                    $sourceId = $sourceKey && isset($qIdMap[$sourceKey]) ? $qIdMap[$sourceKey] : ($r['source_question_id'] ?? null);
                    if (!$sourceId) continue;
                    $db->execute(
                        'INSERT INTO QUESTION_RULES (QUESTION_ID, SOURCE_QUESTION_ID, OPERATOR, COMPARE_VALUE, ACTION)
                         VALUES (:q, :s, :op, :cv, :ac)',
                        [
                            'q' => $qid, 's' => (int)$sourceId,
                            'op' => strtoupper($r['operator'] ?? 'EQ'),
                            'cv' => $r['compare_value'] ?? null,
                            'ac' => strtoupper($r['action'] ?? 'SHOW'),
                        ]
                    );
                }
            }

            // 5. audiences
            if (array_key_exists('audiences', $payload)) {
                $db->execute('DELETE FROM FORM_AUDIENCES WHERE FORM_ID = :f', ['f' => $formId]);
                foreach ((array)$payload['audiences'] as $a) {
                    $type = strtoupper($a['principal_type'] ?? '');
                    $val  = trim((string)($a['principal_value'] ?? ''));
                    if (!in_array($type, ['USER','AD_GROUP'], true) || $val === '') continue;
                    $db->execute(
                        'INSERT INTO FORM_AUDIENCES (FORM_ID, PRINCIPAL_TYPE, PRINCIPAL_VALUE) VALUES (:f, :t, :v)',
                        ['f' => $formId, 't' => $type, 'v' => $val]
                    );
                }
            }

            return [
                'form_id'  => $formId,
                'page_map' => $pageIdMap,
                'q_map'    => $qIdMap,
            ];
        });
    }
}
