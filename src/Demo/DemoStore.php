<?php
declare(strict_types=1);

namespace App\Demo;

/**
 * Demo 模式儲存層：用 JSON 檔取代 Oracle，提供 ID 自動遞增與簡易交易（純 in-memory）。
 * 結構鏡射 schema.sql 中的資料表，所有欄位採用小寫 key 以對齊 OciConnection::normalizeRow 行為。
 */
class DemoStore
{
    public string $file;
    /** @var array<string,mixed> */
    public array $data = [];
    private int $txDepth = 0;
    /** @var array<string,mixed>|null */
    private ?array $snapshot = null;

    public function __construct(string $file)
    {
        $this->file = $file;
        $this->load();
    }

    private function load(): void
    {
        if (!is_file($this->file)) {
            $dir = dirname($this->file);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $this->data = $this->seed();
            $this->save();
            return;
        }
        $raw = (string)@file_get_contents($this->file);
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            $this->data = $this->seed();
            $this->save();
            return;
        }
        $this->data = $j;
    }

    public function save(): void
    {
        if ($this->txDepth > 0) return; // 等 commit
        @file_put_contents(
            $this->file,
            json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /**
     * 簡易交易：拍快照、若 callback 拋例外則 rollback。
     */
    public function transaction(callable $fn)
    {
        $this->txDepth++;
        if ($this->txDepth === 1) $this->snapshot = json_decode(json_encode($this->data), true);
        try {
            $r = $fn($this);
            $this->txDepth--;
            if ($this->txDepth === 0) {
                $this->snapshot = null;
                $this->save();
            }
            return $r;
        } catch (\Throwable $e) {
            // rollback
            if ($this->txDepth === 1 && $this->snapshot !== null) {
                $this->data = $this->snapshot;
            }
            $this->txDepth = max(0, $this->txDepth - 1);
            $this->snapshot = null;
            throw $e;
        }
    }

    public function nextId(string $table): int
    {
        $this->data['_seq'][$table] = (int)($this->data['_seq'][$table] ?? 0) + 1;
        return (int)$this->data['_seq'][$table];
    }

    /** @return array<int,array<string,mixed>> */
    public function table(string $name): array
    {
        return $this->data[$name] ?? [];
    }

    public function setTable(string $name, array $rows): void
    {
        $this->data[$name] = array_values($rows);
    }

    public function reset(): void
    {
        $this->data = $this->seed();
        $this->save();
    }

    private function ts(int $offsetSec = 0): string
    {
        return date('Y-m-d H:i:s', time() + $offsetSec);
    }

    /**
     * 種子資料：2 名使用者、3 張表單（含 PUBLISHED 全題型範本與 2 筆回應）。
     */
    private function seed(): array
    {
        $now = $this->ts();
        $earlier = $this->ts(-86400 * 3);

        $data = [
            '_seq' => [
                'users' => 0, 'forms' => 0, 'form_pages' => 0, 'questions' => 0,
                'question_options' => 0, 'question_rules' => 0, 'form_audiences' => 0,
                'responses' => 0, 'answers' => 0, 'answer_options' => 0,
            ],
            'users' => [],
            'forms' => [],
            'form_pages' => [],
            'questions' => [],
            'question_options' => [],
            'question_rules' => [],
            'form_audiences' => [],
            'responses' => [],
            'answers' => [],
            'answer_options' => [],
        ];

        // === Users ===
        $admin = ['id' => 1, 'username' => 'admin', 'display_name' => '管理員 Admin',
                  'email' => 'admin@demo.local', 'role' => 'ADMIN', 'last_login_at' => $now];
        $user1 = ['id' => 2, 'username' => 'user1', 'display_name' => '王小明',
                  'email' => 'user1@demo.local', 'role' => 'USER', 'last_login_at' => $now];
        $user2 = ['id' => 3, 'username' => 'user2', 'display_name' => '陳小華',
                  'email' => 'user2@demo.local', 'role' => 'USER', 'last_login_at' => $now];
        $data['users'] = [$admin, $user1, $user2];
        $data['_seq']['users'] = 3;

        // === Form 1: 員工滿意度調查 (PUBLISHED, 全題型) ===
        $f1 = [
            'id' => 1, 'title' => '2026 員工滿意度調查',
            'description' => '請針對近一季工作環境給予建議，問卷預計 3 分鐘完成。',
            'status' => 'PUBLISHED', 'audience_mode' => 'PUBLIC',
            'require_login' => 'N', 'allow_multi' => 'N', 'locale_default' => 'zh-TW',
            'start_at' => null, 'end_at' => null,
            'created_by' => 1, 'created_at' => $earlier, 'updated_at' => $now,
            'published_at' => $now,
        ];
        $data['forms'][] = $f1;

        // 預設一頁
        $data['form_pages'][] = ['id' => 1, 'form_id' => 1, 'sort_no' => 0, 'title' => '基本資訊'];
        $data['_seq']['form_pages'] = 1;

        $qid = 0;
        $oid = 0;
        $rid = 0;
        $mkQ = function (array $a) use (&$qid, &$data) {
            $qid++;
            $row = array_merge([
                'id' => $qid, 'form_id' => 1, 'page_id' => 1, 'sort_no' => $qid - 1,
                'help_text' => null, 'required' => 'N', 'config_json' => '{}',
            ], $a);
            $data['questions'][] = $row;
            return $qid;
        };
        $mkOpt = function (int $q, string $label, string $value, int $sn) use (&$oid, &$data) {
            $oid++;
            $data['question_options'][] = ['id' => $oid, 'question_id' => $q, 'sort_no' => $sn, 'label' => $label, 'opt_value' => $value];
            return $oid;
        };
        $mkRule = function (int $q, int $src, string $op, string $val, string $ac) use (&$rid, &$data) {
            $rid++;
            $data['question_rules'][] = ['id' => $rid, 'question_id' => $q, 'source_question_id' => $src,
                                         'operator' => $op, 'compare_value' => $val, 'action' => $ac];
        };

        $q1 = $mkQ(['type' => 'TEXT', 'label' => '您的姓名', 'required' => 'Y',
                    'config_json' => json_encode(['placeholder' => '王小明'], JSON_UNESCAPED_UNICODE)]);
        $q2 = $mkQ(['type' => 'TEXTAREA', 'label' => '對工作環境的建議', 'help_text' => '可寫具體事例，至多 500 字',
                    'config_json' => json_encode(['placeholder' => '...'], JSON_UNESCAPED_UNICODE)]);
        $q3 = $mkQ(['type' => 'SELECT', 'label' => '您的部門', 'required' => 'Y']);
        $mkOpt($q3, '研發部', 'rd', 0); $mkOpt($q3, '行銷部', 'mkt', 1);
        $mkOpt($q3, '人資部', 'hr', 2);  $mkOpt($q3, '財務部', 'fin', 3);
        $q4 = $mkQ(['type' => 'RADIO', 'label' => '整體滿意度', 'required' => 'Y']);
        $mkOpt($q4, '非常滿意', '5', 0); $mkOpt($q4, '滿意', '4', 1);
        $mkOpt($q4, '普通', '3', 2);     $mkOpt($q4, '不滿意', '2', 3); $mkOpt($q4, '非常不滿意', '1', 4);
        $q5 = $mkQ(['type' => 'CHECKBOX', 'label' => '哪些福利對您最重要？（可複選）',
                    'config_json' => json_encode(['min' => 1, 'max' => 3], JSON_UNESCAPED_UNICODE)]);
        $mkOpt($q5, '彈性工時', 'flex', 0);  $mkOpt($q5, '遠端工作', 'remote', 1);
        $mkOpt($q5, '健檢補助', 'health', 2); $mkOpt($q5, '進修補助', 'edu', 3);
        $mkOpt($q5, '股票選擇權', 'stock', 4);
        $q6 = $mkQ(['type' => 'DATE', 'label' => '您預期完成 OKR 的日期']);
        $q7 = $mkQ(['type' => 'NUMBER', 'label' => '在公司年資（年）',
                    'config_json' => json_encode(['min' => 0, 'max' => 50], JSON_UNESCAPED_UNICODE)]);
        $q8 = $mkQ(['type' => 'RATING', 'label' => '請為公司文化打分（1–5 顆星）', 'required' => 'Y',
                    'config_json' => json_encode(['stars' => 5], JSON_UNESCAPED_UNICODE)]);
        $q9 = $mkQ(['type' => 'FILE', 'label' => '附件（選填，pdf/jpg/png）',
                    'config_json' => json_encode(['accept' => '.pdf,.jpg,.png'], JSON_UNESCAPED_UNICODE)]);
        // 規則：選擇「不滿意 / 非常不滿意」時才顯示「對工作環境的建議」（q2）
        $mkRule($q2, $q4, 'IN', '1,2', 'SHOW');
        $data['_seq']['questions'] = $qid;
        $data['_seq']['question_options'] = $oid;
        $data['_seq']['question_rules'] = $rid;

        // === Form 1 Responses ===
        $resp1 = ['id' => 1, 'form_id' => 1, 'user_id' => 2, 'status' => 'SUBMITTED',
                  'ip_addr' => '192.168.1.10', 'user_agent' => 'Mozilla/5.0 Demo',
                  'anon_token' => null, 'submitted_at' => $this->ts(-3600 * 5)];
        $resp2 = ['id' => 2, 'form_id' => 1, 'user_id' => 3, 'status' => 'SUBMITTED',
                  'ip_addr' => '192.168.1.11', 'user_agent' => 'Mozilla/5.0 Demo',
                  'anon_token' => null, 'submitted_at' => $this->ts(-3600 * 2)];
        $resp3 = ['id' => 3, 'form_id' => 1, 'user_id' => null, 'status' => 'SUBMITTED',
                  'ip_addr' => '203.0.113.5', 'user_agent' => 'Mozilla/5.0 Demo',
                  'anon_token' => 'anon_demo_3', 'submitted_at' => $this->ts(-1800)];
        $data['responses'] = [$resp1, $resp2, $resp3];
        $data['_seq']['responses'] = 3;

        // 答案資料（精簡：以 helper 寫入）
        $aSeq = 0;
        $aoSeq = 0;
        $writeAns = function (int $respId, int $qid, array $vals, ?array $optIds = null) use (&$aSeq, &$aoSeq, &$data) {
            $aSeq++;
            $data['answers'][] = array_merge([
                'id' => $aSeq, 'response_id' => $respId, 'question_id' => $qid,
                'value_text' => null, 'value_number' => null, 'value_date' => null,
                'file_path' => null, 'file_name' => null,
            ], $vals);
            if ($optIds) {
                foreach ($optIds as $o) {
                    $aoSeq++;
                    $data['answer_options'][] = ['id' => $aoSeq, 'answer_id' => $aSeq, 'option_id' => $o];
                }
            }
        };
        // Resp 1: 王小明 / 研發部 / 滿意 / flex+remote / 5 顆星
        $writeAns(1, 1, ['value_text' => '王小明']);
        $writeAns(1, 3, [], [1]);             // 研發部
        $writeAns(1, 4, [], [5]);             // 滿意
        $writeAns(1, 5, [], [10, 11]);        // flex, remote
        $writeAns(1, 6, ['value_date' => $this->ts(86400 * 90)]);
        $writeAns(1, 7, ['value_number' => 3]);
        $writeAns(1, 8, ['value_number' => 5]);
        // Resp 2: 陳小華 / 行銷部 / 不滿意 + 建議 / health+edu / 4 顆星
        $writeAns(2, 1, ['value_text' => '陳小華']);
        $writeAns(2, 2, ['value_text' => '希望增加休息空間與咖啡機補給。']);
        $writeAns(2, 3, [], [2]);             // 行銷部
        $writeAns(2, 4, [], [8]);             // 不滿意（opt id 順序 5/6/7/8/9 對應滿意度 5..1，opt 8 = '不滿意'）
        $writeAns(2, 5, [], [12, 13]);        // health, edu
        $writeAns(2, 7, ['value_number' => 5]);
        $writeAns(2, 8, ['value_number' => 4]);
        // Resp 3: 匿名 / 人資部 / 普通 / flex / 3 顆星
        $writeAns(3, 1, ['value_text' => '匿名同事']);
        $writeAns(3, 3, [], [3]);             // 人資部
        $writeAns(3, 4, [], [7]);             // 普通
        $writeAns(3, 5, [], [10]);            // flex
        $writeAns(3, 7, ['value_number' => 1]);
        $writeAns(3, 8, ['value_number' => 3]);
        $data['_seq']['answers'] = $aSeq;
        $data['_seq']['answer_options'] = $aoSeq;

        // === Form 2: 教育訓練報名 (DRAFT) ===
        $data['forms'][] = [
            'id' => 2, 'title' => '2026 Q2 教育訓練報名',
            'description' => '尚在編輯中，內含報名相關欄位。',
            'status' => 'DRAFT', 'audience_mode' => 'USER',
            'require_login' => 'Y', 'allow_multi' => 'N', 'locale_default' => 'zh-TW',
            'start_at' => null, 'end_at' => null,
            'created_by' => 1, 'created_at' => $earlier, 'updated_at' => $earlier,
            'published_at' => null,
        ];
        $qid++;
        $data['questions'][] = ['id' => $qid, 'form_id' => 2, 'page_id' => null, 'sort_no' => 0,
                                'type' => 'TEXT', 'label' => '姓名', 'help_text' => null,
                                'required' => 'Y', 'config_json' => '{}'];
        $qid++;
        $data['questions'][] = ['id' => $qid, 'form_id' => 2, 'page_id' => null, 'sort_no' => 1,
                                'type' => 'SELECT', 'label' => '報名場次', 'help_text' => null,
                                'required' => 'Y', 'config_json' => '{}'];
        $oid++;
        $data['question_options'][] = ['id' => $oid, 'question_id' => $qid, 'sort_no' => 0, 'label' => '4/15 上午', 'opt_value' => 'a'];
        $oid++;
        $data['question_options'][] = ['id' => $oid, 'question_id' => $qid, 'sort_no' => 1, 'label' => '4/15 下午', 'opt_value' => 'b'];
        $data['form_audiences'][] = ['id' => 1, 'form_id' => 2, 'principal_type' => 'USER', 'principal_value' => 'user1'];
        $data['_seq']['form_audiences'] = 1;
        $data['_seq']['questions'] = $qid;
        $data['_seq']['question_options'] = $oid;

        // === Form 3: 已結案問卷 (CLOSED) ===
        $data['forms'][] = [
            'id' => 3, 'title' => '春酒抽獎登記（已結案）',
            'description' => '此活動已結束。', 'status' => 'CLOSED',
            'audience_mode' => 'PUBLIC', 'require_login' => 'N', 'allow_multi' => 'N',
            'locale_default' => 'zh-TW', 'start_at' => null, 'end_at' => $this->ts(-86400 * 7),
            'created_by' => 1, 'created_at' => $this->ts(-86400 * 30),
            'updated_at' => $this->ts(-86400 * 7), 'published_at' => $this->ts(-86400 * 30),
        ];
        $data['_seq']['forms'] = 3;

        return $data;
    }
}
