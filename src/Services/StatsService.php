<?php
declare(strict_types=1);

namespace App\Services;

use App\Db\OciConnection;

class StatsService
{
    public function __construct(private OciConnection $db) {}

    /**
     * 對表單每個題目產出聚合資料供前端 Chart.js 使用。
     *
     * @return array<int,array<string,mixed>>  key: question_id
     */
    public function aggregate(int $formId): array
    {
        $questions = $this->db->fetchAll(
            'SELECT * FROM QUESTIONS WHERE FORM_ID = :f ORDER BY SORT_NO, ID',
            ['f' => $formId]
        );
        $result = [];
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $type = $q['type'];
            $entry = ['question' => $q];
            switch ($type) {
                case 'SELECT':
                case 'RADIO':
                case 'CHECKBOX':
                    $rows = $this->db->fetchAll(
                        "SELECT O.ID, O.LABEL, COUNT(AO.ID) AS CNT
                         FROM QUESTION_OPTIONS O
                         LEFT JOIN ANSWERS A ON A.QUESTION_ID = O.QUESTION_ID
                         LEFT JOIN ANSWER_OPTIONS AO ON AO.OPTION_ID = O.ID AND AO.ANSWER_ID = A.ID
                         WHERE O.QUESTION_ID = :q
                         GROUP BY O.ID, O.LABEL, O.SORT_NO
                         ORDER BY O.SORT_NO, O.ID",
                        ['q' => $qid]
                    );
                    $entry['kind'] = 'options';
                    $entry['data'] = $rows;
                    break;
                case 'RATING':
                case 'NUMBER':
                    $row = $this->db->fetchOne(
                        'SELECT COUNT(VALUE_NUMBER) AS CNT, AVG(VALUE_NUMBER) AS AVG_V,
                                MIN(VALUE_NUMBER) AS MIN_V, MAX(VALUE_NUMBER) AS MAX_V
                         FROM ANSWERS WHERE QUESTION_ID = :q',
                        ['q' => $qid]
                    );
                    $entry['kind'] = 'numeric';
                    $entry['data'] = $row;
                    if ($type === 'RATING') {
                        $hist = $this->db->fetchAll(
                            'SELECT VALUE_NUMBER AS V, COUNT(*) AS CNT FROM ANSWERS
                             WHERE QUESTION_ID = :q AND VALUE_NUMBER IS NOT NULL
                             GROUP BY VALUE_NUMBER ORDER BY VALUE_NUMBER',
                            ['q' => $qid]
                        );
                        $entry['histogram'] = $hist;
                    }
                    break;
                case 'TEXT':
                case 'TEXTAREA':
                    $rows = $this->db->fetchAll(
                        "SELECT VALUE_TEXT FROM ANSWERS WHERE QUESTION_ID = :q AND VALUE_TEXT IS NOT NULL
                         ORDER BY ID DESC FETCH FIRST 50 ROWS ONLY",
                        ['q' => $qid]
                    );
                    $entry['kind'] = 'text';
                    $entry['data'] = $rows;
                    break;
                case 'DATE':
                    $rows = $this->db->fetchAll(
                        "SELECT TO_CHAR(VALUE_DATE, 'YYYY-MM-DD') AS D, COUNT(*) AS CNT
                         FROM ANSWERS WHERE QUESTION_ID = :q AND VALUE_DATE IS NOT NULL
                         GROUP BY TO_CHAR(VALUE_DATE, 'YYYY-MM-DD') ORDER BY D",
                        ['q' => $qid]
                    );
                    $entry['kind'] = 'date';
                    $entry['data'] = $rows;
                    break;
                case 'FILE':
                    $cnt = (int) $this->db->scalar(
                        'SELECT COUNT(*) FROM ANSWERS WHERE QUESTION_ID = :q AND FILE_PATH IS NOT NULL',
                        ['q' => $qid]
                    );
                    $entry['kind'] = 'file';
                    $entry['data'] = ['count' => $cnt];
                    break;
            }
            $result[$qid] = $entry;
        }
        return $result;
    }
}
