<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\OciConnection;

class ResponseRepository
{
    public function __construct(private OciConnection $db) {}

    public function createResponse(int $formId, ?int $userId, string $ip, string $ua, ?string $anonToken = null): int
    {
        return $this->db->insertReturningId(
            'INSERT INTO RESPONSES (FORM_ID, USER_ID, STATUS, IP_ADDR, USER_AGENT, ANON_TOKEN)
             VALUES (:f, :u, :s, :ip, :ua, :tok)
             RETURNING ID INTO :new_id',
            ['f' => $formId, 'u' => $userId, 's' => 'SUBMITTED', 'ip' => $ip, 'ua' => substr($ua, 0, 500), 'tok' => $anonToken]
        );
    }

    /**
     * @param array<int,array<string,mixed>> $normalized   key: question_id
     */
    public function writeAnswers(int $responseId, array $normalized, callable $fileResolver): void
    {
        foreach ($normalized as $qid => $a) {
            $valueText = $a['value_text'] ?? null;
            $valueNum  = $a['value_number'] ?? null;
            $valueDate = $a['value_date'] ?? null;
            $filePath = null; $fileName = null;
            if (isset($a['file'])) {
                [$filePath, $fileName] = $fileResolver((int)$qid, $a['file']);
            }
            $ansId = $this->db->insertReturningId(
                "INSERT INTO ANSWERS (RESPONSE_ID, QUESTION_ID, VALUE_TEXT, VALUE_NUMBER, VALUE_DATE, FILE_PATH, FILE_NAME)
                 VALUES (:r, :q, :vt, :vn, CASE WHEN :vd IS NULL THEN NULL ELSE TO_TIMESTAMP(:vd, 'YYYY-MM-DD HH24:MI:SS') END, :fp, :fn)
                 RETURNING ID INTO :new_id",
                [
                    'r'  => $responseId, 'q' => (int)$qid,
                    'vt' => $valueText !== null ? ['value' => (string)$valueText, 'type' => SQLT_CHR, 'length' => -1] : null,
                    'vn' => $valueNum,
                    'vd' => $valueDate,
                    'fp' => $filePath, 'fn' => $fileName,
                ]
            );
            if (!empty($a['option_ids'])) {
                foreach ($a['option_ids'] as $oid) {
                    $this->db->execute(
                        'INSERT INTO ANSWER_OPTIONS (ANSWER_ID, OPTION_ID) VALUES (:a, :o)',
                        ['a' => $ansId, 'o' => (int)$oid]
                    );
                }
            }
        }
    }

    public function countByUser(int $formId, int $userId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM RESPONSES WHERE FORM_ID = :f AND USER_ID = :u AND STATUS = 'SUBMITTED'",
            ['f' => $formId, 'u' => $userId]
        );
    }

    public function countByAnonToken(int $formId, string $token): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM RESPONSES WHERE FORM_ID = :f AND ANON_TOKEN = :t AND STATUS = 'SUBMITTED'",
            ['f' => $formId, 't' => $token]
        );
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginate(int $formId, int $page, int $perPage, string $sort = 'submitted_at', string $dir = 'DESC'): array
    {
        $allow = ['submitted_at' => 'R.SUBMITTED_AT', 'user' => 'U.DISPLAY_NAME', 'id' => 'R.ID'];
        $col = $allow[$sort] ?? 'R.SUBMITTED_AT';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM RESPONSES WHERE FORM_ID = :f AND STATUS = 'SUBMITTED'",
            ['f' => $formId]
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT R.ID, R.SUBMITTED_AT, R.IP_ADDR, R.USER_ID, U.DISPLAY_NAME AS USER_NAME, U.USERNAME
             FROM RESPONSES R LEFT JOIN USERS U ON U.ID = R.USER_ID
             WHERE R.FORM_ID = :f AND R.STATUS = 'SUBMITTED'
             ORDER BY {$col} {$dir}
             OFFSET :off ROWS FETCH NEXT :limit ROWS ONLY",
            ['f' => $formId, 'off' => $offset, 'limit' => $perPage]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * 取單筆 response + 所有 answers（含 option labels）
     *
     * @return array<string,mixed>|null
     */
    public function find(int $responseId): ?array
    {
        $r = $this->db->fetchOne(
            'SELECT R.*, U.DISPLAY_NAME AS USER_NAME, U.USERNAME
             FROM RESPONSES R LEFT JOIN USERS U ON U.ID = R.USER_ID
             WHERE R.ID = :id',
            ['id' => $responseId]
        );
        if (!$r) return null;
        $r['answers'] = $this->db->fetchAll(
            'SELECT A.*, Q.LABEL AS Q_LABEL, Q.TYPE AS Q_TYPE
             FROM ANSWERS A JOIN QUESTIONS Q ON Q.ID = A.QUESTION_ID
             WHERE A.RESPONSE_ID = :r ORDER BY Q.SORT_NO, Q.ID',
            ['r' => $responseId]
        );
        // option labels
        if ($r['answers']) {
            $ansIds = array_column($r['answers'], 'id');
            $in = implode(',', array_map('intval', $ansIds));
            $opts = $this->db->fetchAll(
                "SELECT AO.ANSWER_ID, O.ID AS OPTION_ID, O.LABEL
                 FROM ANSWER_OPTIONS AO JOIN QUESTION_OPTIONS O ON O.ID = AO.OPTION_ID
                 WHERE AO.ANSWER_ID IN ({$in})"
            );
            $byAns = [];
            foreach ($opts as $o) $byAns[(int)$o['answer_id']][] = $o['label'];
            foreach ($r['answers'] as &$a) {
                $a['option_labels'] = $byAns[(int)$a['id']] ?? [];
            }
        }
        return $r;
    }

    public function delete(int $responseId): void
    {
        $this->db->execute('DELETE FROM RESPONSES WHERE ID = :id', ['id' => $responseId]);
    }
}
