<?php
declare(strict_types=1);

namespace App\Demo;

class DemoExporter
{
    public function __construct(private DemoStore $store) {}

    private static function safeCell($v): string
    {
        $s = (string)($v ?? '');
        if ($s === '') return $s;
        $first = $s[0];
        if ($first === '=' || $first === '+' || $first === '-' || $first === '@'
            || $first === "\t" || $first === "\r") {
            return "'" . $s;
        }
        return $s;
    }

    public function streamCsv(int $formId): void
    {
        $questions = array_values(array_filter($this->store->table('questions'), fn($r) => (int)$r['form_id'] === $formId));
        usort($questions, fn($a, $b) => ((int)$a['sort_no'] - (int)$b['sort_no']) ?: ((int)$a['id'] - (int)$b['id']));

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $header = ['response_id', 'submitted_at', 'user', 'ip'];
        foreach ($questions as $q) $header[] = $q['label'];
        fputcsv($out, array_map([self::class, 'safeCell'], $header), ',', '"', '\\');

        $users = [];
        foreach ($this->store->table('users') as $u) $users[(int)$u['id']] = $u;
        $optById = [];
        foreach ($this->store->table('question_options') as $o) $optById[(int)$o['id']] = $o;

        $responses = array_values(array_filter($this->store->table('responses'),
            fn($r) => (int)$r['form_id'] === $formId && ($r['status'] ?? '') === 'SUBMITTED'));
        usort($responses, fn($a, $b) => strcmp((string)$a['submitted_at'], (string)$b['submitted_at']));

        foreach ($responses as $r) {
            $rid = (int)$r['id'];
            $byQ = [];
            foreach ($this->store->table('answers') as $a) {
                if ((int)$a['response_id'] === $rid) $byQ[(int)$a['question_id']] = $a;
            }
            $optByAns = [];
            foreach ($this->store->table('answer_options') as $ao) {
                $aid = (int)$ao['answer_id'];
                if (isset($byQ[$aid])) {} // 不可能
                $optByAns[$aid][] = $optById[(int)$ao['option_id']]['label'] ?? '';
            }
            $u = $r['user_id'] !== null ? ($users[(int)$r['user_id']] ?? null) : null;
            $row = [
                $rid, $r['submitted_at'],
                $u['display_name'] ?? $u['username'] ?? '',
                $r['ip_addr'] ?? '',
            ];
            foreach ($questions as $q) {
                $qid = (int)$q['id'];
                $a = $byQ[$qid] ?? null;
                if (!$a) { $row[] = ''; continue; }
                if (in_array($q['type'], ['SELECT','RADIO','CHECKBOX'], true)) {
                    $row[] = implode('; ', $optByAns[(int)$a['id']] ?? []);
                } elseif (in_array($q['type'], ['NUMBER','RATING'], true)) {
                    $row[] = $a['value_number'];
                } elseif ($q['type'] === 'DATE') {
                    $row[] = $a['value_date'];
                } elseif ($q['type'] === 'FILE') {
                    $row[] = $a['file_name'] ?? '';
                } else {
                    $row[] = $a['value_text'] ?? '';
                }
            }
            fputcsv($out, array_map([self::class, 'safeCell'], $row), ',', '"', '\\');
        }
        fclose($out);
    }
}
