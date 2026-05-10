<?php
declare(strict_types=1);

namespace App\Demo;

class DemoStatsService
{
    public function __construct(private DemoStore $store) {}

    public function aggregate(int $formId): array
    {
        $questions = array_values(array_filter($this->store->table('questions'), fn($r) => (int)$r['form_id'] === $formId));
        usort($questions, fn($a, $b) => ((int)$a['sort_no'] - (int)$b['sort_no']) ?: ((int)$a['id'] - (int)$b['id']));

        $optsByQ = [];
        foreach ($this->store->table('question_options') as $o) $optsByQ[(int)$o['question_id']][(int)$o['id']] = $o;
        // answers index
        $answersByQ = [];
        foreach ($this->store->table('answers') as $a) $answersByQ[(int)$a['question_id']][] = $a;
        $aoByAns = [];
        foreach ($this->store->table('answer_options') as $ao) $aoByAns[(int)$ao['answer_id']][] = (int)$ao['option_id'];

        $result = [];
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $type = $q['type'];
            $entry = ['question' => $q, 'q' => ['id' => $qid, 'label' => $q['label'], 'type' => $type]];
            switch ($type) {
                case 'SELECT': case 'RADIO': case 'CHECKBOX':
                    $rows = [];
                    $opts = $optsByQ[$qid] ?? [];
                    uasort($opts, fn($a, $b) => ((int)$a['sort_no'] - (int)$b['sort_no']) ?: ((int)$a['id'] - (int)$b['id']));
                    foreach ($opts as $o) {
                        $cnt = 0;
                        foreach (($answersByQ[$qid] ?? []) as $a) {
                            if (in_array((int)$o['id'], $aoByAns[(int)$a['id']] ?? [], true)) $cnt++;
                        }
                        $rows[] = ['id' => $o['id'], 'label' => $o['label'], 'cnt' => $cnt];
                    }
                    $entry['kind'] = 'options';
                    $entry['data'] = $rows;
                    break;
                case 'NUMBER': case 'RATING':
                    $vals = [];
                    foreach (($answersByQ[$qid] ?? []) as $a) {
                        if ($a['value_number'] !== null) $vals[] = (float)$a['value_number'];
                    }
                    $entry['kind'] = 'numeric';
                    $entry['data'] = $vals
                        ? ['count' => count($vals), 'avg' => array_sum($vals) / count($vals), 'min' => min($vals), 'max' => max($vals)]
                        : ['count' => 0, 'avg' => 0, 'min' => 0, 'max' => 0];
                    break;
                case 'TEXT': case 'TEXTAREA':
                    $samples = [];
                    foreach (array_reverse($answersByQ[$qid] ?? []) as $a) {
                        if (!empty($a['value_text'])) $samples[] = $a['value_text'];
                        if (count($samples) >= 50) break;
                    }
                    $entry['kind'] = 'text';
                    $entry['data'] = $samples;
                    break;
                case 'DATE':
                    $byDay = [];
                    foreach (($answersByQ[$qid] ?? []) as $a) {
                        if (!empty($a['value_date'])) {
                            $d = substr((string)$a['value_date'], 0, 10);
                            $byDay[$d] = ($byDay[$d] ?? 0) + 1;
                        }
                    }
                    ksort($byDay);
                    $entry['kind'] = 'date';
                    $entry['data'] = array_map(fn($d, $c) => ['day' => $d, 'cnt' => $c], array_keys($byDay), array_values($byDay));
                    break;
                case 'FILE':
                    $cnt = 0;
                    foreach (($answersByQ[$qid] ?? []) as $a) if (!empty($a['file_path'])) $cnt++;
                    $entry['kind'] = 'file';
                    $entry['data'] = ['count' => $cnt];
                    break;
                default:
                    $entry['kind'] = 'text';
                    $entry['data'] = [];
            }
            $result[$qid] = $entry;
        }
        return $result;
    }
}
