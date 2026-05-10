<?php
declare(strict_types=1);

namespace App\Demo;

class DemoResponseRepository
{
    public function __construct(private DemoStore $store) {}

    public function createResponse(int $formId, ?int $userId, string $ip, string $ua, ?string $anonToken = null): int
    {
        $id = $this->store->nextId('responses');
        $rows = $this->store->table('responses');
        $rows[] = [
            'id' => $id, 'form_id' => $formId, 'user_id' => $userId,
            'status' => 'SUBMITTED', 'ip_addr' => $ip, 'user_agent' => substr($ua, 0, 500),
            'anon_token' => $anonToken, 'submitted_at' => date('Y-m-d H:i:s'),
        ];
        $this->store->setTable('responses', $rows);
        $this->store->save();
        return $id;
    }

    public function writeAnswers(int $responseId, array $normalized, callable $fileResolver): void
    {
        $ans = $this->store->table('answers');
        $ao = $this->store->table('answer_options');
        foreach ($normalized as $qid => $a) {
            $aid = $this->store->nextId('answers');
            $filePath = null; $fileName = null;
            if (isset($a['file'])) {
                [$filePath, $fileName] = $fileResolver((int)$qid, $a['file']);
            }
            $ans[] = [
                'id' => $aid, 'response_id' => $responseId, 'question_id' => (int)$qid,
                'value_text' => $a['value_text'] ?? null,
                'value_number' => $a['value_number'] ?? null,
                'value_date' => $a['value_date'] ?? null,
                'file_path' => $filePath, 'file_name' => $fileName,
            ];
            if (!empty($a['option_ids'])) {
                foreach ($a['option_ids'] as $oid) {
                    $aoid = $this->store->nextId('answer_options');
                    $ao[] = ['id' => $aoid, 'answer_id' => $aid, 'option_id' => (int)$oid];
                }
            }
        }
        $this->store->setTable('answers', $ans);
        $this->store->setTable('answer_options', $ao);
        $this->store->save();
    }

    public function countByUser(int $formId, int $userId): int
    {
        $n = 0;
        foreach ($this->store->table('responses') as $r) {
            if ((int)$r['form_id'] === $formId && (int)($r['user_id'] ?? 0) === $userId && ($r['status'] ?? '') === 'SUBMITTED') $n++;
        }
        return $n;
    }

    public function countByAnonToken(int $formId, string $token): int
    {
        $n = 0;
        foreach ($this->store->table('responses') as $r) {
            if ((int)$r['form_id'] === $formId && ($r['anon_token'] ?? null) === $token && ($r['status'] ?? '') === 'SUBMITTED') $n++;
        }
        return $n;
    }

    public function paginate(int $formId, int $page, int $perPage, string $sort = 'submitted_at', string $dir = 'DESC'): array
    {
        $users = [];
        foreach ($this->store->table('users') as $u) $users[(int)$u['id']] = $u;
        $rows = [];
        foreach ($this->store->table('responses') as $r) {
            if ((int)$r['form_id'] !== $formId || ($r['status'] ?? '') !== 'SUBMITTED') continue;
            $u = $r['user_id'] !== null ? ($users[(int)$r['user_id']] ?? null) : null;
            $rows[] = [
                'id' => $r['id'], 'submitted_at' => $r['submitted_at'], 'ip_addr' => $r['ip_addr'],
                'user_id' => $r['user_id'],
                'user_name' => $u['display_name'] ?? null,
                'username'  => $u['username'] ?? null,
            ];
        }
        $allow = ['submitted_at','user','id'];
        $sortKey = in_array($sort, $allow, true) ? $sort : 'submitted_at';
        $dirN = strtoupper($dir) === 'ASC' ? 1 : -1;
        usort($rows, function ($a, $b) use ($sortKey, $dirN) {
            $av = $sortKey === 'user' ? (string)($a['user_name'] ?? '') : $a[$sortKey === 'submitted_at' ? 'submitted_at' : 'id'];
            $bv = $sortKey === 'user' ? (string)($b['user_name'] ?? '') : $b[$sortKey === 'submitted_at' ? 'submitted_at' : 'id'];
            if ($av == $bv) return 0;
            return ($av < $bv ? -1 : 1) * $dirN;
        });
        $total = count($rows);
        $rows = array_slice($rows, max(0, ($page - 1) * $perPage), $perPage);
        return ['rows' => $rows, 'total' => $total];
    }

    public function find(int $responseId): ?array
    {
        $resp = null;
        foreach ($this->store->table('responses') as $r) {
            if ((int)$r['id'] === $responseId) { $resp = $r; break; }
        }
        if (!$resp) return null;
        $users = [];
        foreach ($this->store->table('users') as $u) $users[(int)$u['id']] = $u;
        $u = $resp['user_id'] !== null ? ($users[(int)$resp['user_id']] ?? null) : null;
        $resp['user_name'] = $u['display_name'] ?? null;
        $resp['username']  = $u['username'] ?? null;

        $qById = [];
        foreach ($this->store->table('questions') as $q) $qById[(int)$q['id']] = $q;
        $optById = [];
        foreach ($this->store->table('question_options') as $o) $optById[(int)$o['id']] = $o;

        $answers = [];
        foreach ($this->store->table('answers') as $a) {
            if ((int)$a['response_id'] !== $responseId) continue;
            $q = $qById[(int)$a['question_id']] ?? null;
            $a['q_label'] = $q['label'] ?? '';
            $a['q_type']  = $q['type']  ?? 'TEXT';
            $a['option_labels'] = [];
            foreach ($this->store->table('answer_options') as $ao) {
                if ((int)$ao['answer_id'] === (int)$a['id']) {
                    $a['option_labels'][] = $optById[(int)$ao['option_id']]['label'] ?? '';
                }
            }
            $answers[] = $a;
        }
        usort($answers, function ($a, $b) use ($qById) {
            $sa = (int)($qById[(int)$a['question_id']]['sort_no'] ?? 0);
            $sb = (int)($qById[(int)$b['question_id']]['sort_no'] ?? 0);
            return ($sa - $sb) ?: ((int)$a['question_id'] - (int)$b['question_id']);
        });
        $resp['answers'] = $answers;
        return $resp;
    }

    public function delete(int $responseId): void
    {
        $this->store->setTable('responses', array_values(array_filter($this->store->table('responses'), fn($r) => (int)$r['id'] !== $responseId)));
        $aIds = [];
        $kept = [];
        foreach ($this->store->table('answers') as $a) {
            if ((int)$a['response_id'] === $responseId) $aIds[] = (int)$a['id']; else $kept[] = $a;
        }
        $this->store->setTable('answers', $kept);
        $this->store->setTable('answer_options', array_values(array_filter($this->store->table('answer_options'), fn($r) => !in_array((int)$r['answer_id'], $aIds, true))));
        $this->store->save();
    }
}
