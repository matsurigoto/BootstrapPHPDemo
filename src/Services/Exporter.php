<?php
declare(strict_types=1);

namespace App\Services;

use App\Db\OciConnection;

class Exporter
{
    public function __construct(private OciConnection $db) {}

    /**
     * 串流匯出 CSV 到 stdout (UTF-8 BOM)。
     */
    public function streamCsv(int $formId): void
    {
        $questions = $this->db->fetchAll(
            'SELECT * FROM QUESTIONS WHERE FORM_ID = :f ORDER BY SORT_NO, ID',
            ['f' => $formId]
        );
        $optsByQ = [];
        if ($questions) {
            $qIds = implode(',', array_map(fn($q) => (int)$q['id'], $questions));
            $opts = $this->db->fetchAll(
                "SELECT * FROM QUESTION_OPTIONS WHERE QUESTION_ID IN ({$qIds}) ORDER BY QUESTION_ID, SORT_NO, ID"
            );
            foreach ($opts as $o) $optsByQ[(int)$o['question_id']][(int)$o['id']] = $o;
        }

        $out = fopen('php://output', 'w');
        // BOM
        fwrite($out, "\xEF\xBB\xBF");

        $header = ['response_id', 'submitted_at', 'user', 'ip'];
        foreach ($questions as $q) $header[] = $q['label'];
        fputcsv($out, $header);

        // 一次抓所有 responses 與 answers
        $responses = $this->db->fetchAll(
            "SELECT R.ID, R.SUBMITTED_AT, R.IP_ADDR, U.USERNAME, U.DISPLAY_NAME
             FROM RESPONSES R LEFT JOIN USERS U ON U.ID = R.USER_ID
             WHERE R.FORM_ID = :f AND R.STATUS = 'SUBMITTED'
             ORDER BY R.SUBMITTED_AT",
            ['f' => $formId]
        );

        foreach ($responses as $r) {
            $rid = (int)$r['id'];
            $answers = $this->db->fetchAll(
                'SELECT A.* FROM ANSWERS A WHERE A.RESPONSE_ID = :r',
                ['r' => $rid]
            );
            $byQ = [];
            foreach ($answers as $a) $byQ[(int)$a['question_id']] = $a;
            // option labels
            $ansIds = array_map(fn($a) => (int)$a['id'], $answers);
            $optByAns = [];
            if ($ansIds) {
                $in = implode(',', $ansIds);
                $optRows = $this->db->fetchAll(
                    "SELECT AO.ANSWER_ID, O.LABEL FROM ANSWER_OPTIONS AO
                     JOIN QUESTION_OPTIONS O ON O.ID = AO.OPTION_ID
                     WHERE AO.ANSWER_ID IN ({$in})"
                );
                foreach ($optRows as $or) $optByAns[(int)$or['answer_id']][] = $or['label'];
            }
            $row = [
                $rid,
                $r['submitted_at'],
                $r['display_name'] ?? $r['username'] ?? '',
                $r['ip_addr'] ?? '',
            ];
            foreach ($questions as $q) {
                $qid = (int)$q['id'];
                $a = $byQ[$qid] ?? null;
                if (!$a) { $row[] = ''; continue; }
                if (in_array($q['type'], ['SELECT','RADIO','CHECKBOX'], true)) {
                    $row[] = implode('; ', $optByAns[(int)$a['id']] ?? []);
                } elseif ($q['type'] === 'NUMBER' || $q['type'] === 'RATING') {
                    $row[] = $a['value_number'];
                } elseif ($q['type'] === 'DATE') {
                    $row[] = $a['value_date'];
                } elseif ($q['type'] === 'FILE') {
                    $row[] = $a['file_name'] ?? '';
                } else {
                    $row[] = $a['value_text'] ?? '';
                }
            }
            fputcsv($out, $row);
        }
        fclose($out);
    }
}
