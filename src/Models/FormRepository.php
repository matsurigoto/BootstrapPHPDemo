<?php
declare(strict_types=1);

namespace App\Models;

use App\Db\OciConnection;

class FormRepository
{
    public function __construct(private OciConnection $db) {}

    /**
     * 列表查詢（帶搜尋、排序、分頁）。
     *
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginate(?string $q, ?string $status, string $sort, string $dir, int $page, int $perPage, ?int $createdBy = null): array
    {
        $where = [];
        $binds = [];
        if ($q !== null && $q !== '') {
            $where[] = 'LOWER(F.TITLE) LIKE :q';
            $binds['q'] = '%' . strtolower($q) . '%';
        }
        if ($status !== null && $status !== '') {
            $where[] = 'F.STATUS = :st';
            $binds['st'] = $status;
        }
        if ($createdBy !== null) {
            $where[] = 'F.CREATED_BY = :cb';
            $binds['cb'] = $createdBy;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $allowSort = [
            'title'        => 'F.TITLE',
            'status'       => 'F.STATUS',
            'created_at'   => 'F.CREATED_AT',
            'updated_at'   => 'F.UPDATED_AT',
            'start_at'     => 'F.START_AT',
            'end_at'       => 'F.END_AT',
            'responses'    => 'RESPONSES_COUNT',
        ];
        $sortCol = $allowSort[$sort] ?? 'F.UPDATED_AT';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM FORMS F {$whereSql}",
            $binds
        );

        $offset = max(0, ($page - 1) * $perPage);
        $binds['off']   = $offset;
        $binds['limit'] = $perPage;

        $sql = "
            SELECT F.ID, F.TITLE, F.STATUS, F.START_AT, F.END_AT, F.CREATED_AT, F.UPDATED_AT,
                   F.CREATED_BY, U.DISPLAY_NAME AS CREATOR_NAME,
                   (SELECT COUNT(*) FROM RESPONSES R WHERE R.FORM_ID = F.ID AND R.STATUS = 'SUBMITTED') AS RESPONSES_COUNT
            FROM FORMS F
            LEFT JOIN USERS U ON U.ID = F.CREATED_BY
            {$whereSql}
            ORDER BY {$sortCol} {$dir}, F.ID {$dir}
            OFFSET :off ROWS FETCH NEXT :limit ROWS ONLY
        ";
        return [
            'rows'  => $this->db->fetchAll($sql, $binds),
            'total' => $total,
        ];
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT F.*, U.DISPLAY_NAME AS CREATOR_NAME
             FROM FORMS F LEFT JOIN USERS U ON U.ID = F.CREATED_BY
             WHERE F.ID = :id',
            ['id' => $id]
        );
    }

    public function create(int $userId, string $title, ?string $description = null): int
    {
        $sql = "INSERT INTO FORMS (TITLE, DESCRIPTION, CREATED_BY)
                VALUES (:t, EMPTY_CLOB(), :u)
                RETURNING ID INTO :new_id";
        // 為求簡化，先寫空 CLOB；description 之後 update
        $id = $this->db->insertReturningId($sql, ['t' => $title, 'u' => $userId]);
        if ($description !== null && $description !== '') {
            $this->updateDescription($id, $description);
        }
        return $id;
    }

    public function updateBasic(int $id, array $data): void
    {
        $sets = ['UPDATED_AT = SYSTIMESTAMP'];
        $binds = ['id' => $id];
        $map = [
            'title'         => 'TITLE',
            'status'        => 'STATUS',
            'audience_mode' => 'AUDIENCE_MODE',
            'require_login' => 'REQUIRE_LOGIN',
            'allow_multi'   => 'ALLOW_MULTI',
            'start_at'      => 'START_AT',
            'end_at'        => 'END_AT',
            'locale_default'=> 'LOCALE_DEFAULT',
        ];
        foreach ($map as $k => $col) {
            if (array_key_exists($k, $data)) {
                if (in_array($k, ['start_at', 'end_at'], true)) {
                    if ($data[$k] === null || $data[$k] === '') {
                        $sets[] = "$col = NULL";
                    } else {
                        $sets[] = "$col = TO_TIMESTAMP(:$k, 'YYYY-MM-DD\"T\"HH24:MI')";
                        $binds[$k] = $data[$k];
                    }
                } else {
                    $sets[] = "$col = :$k";
                    $binds[$k] = $data[$k];
                }
            }
        }
        $sql = 'UPDATE FORMS SET ' . implode(', ', $sets) . ' WHERE ID = :id';
        $this->db->execute($sql, $binds);
        if (array_key_exists('description', $data)) {
            $this->updateDescription($id, (string)$data['description']);
        }
    }

    public function updateDescription(int $id, string $desc): void
    {
        $this->db->execute(
            'UPDATE FORMS SET DESCRIPTION = :d, UPDATED_AT = SYSTIMESTAMP WHERE ID = :id',
            ['d' => ['value' => $desc, 'type' => SQLT_CHR, 'length' => -1], 'id' => $id]
        );
    }

    public function publish(int $id): void
    {
        $this->db->execute(
            "UPDATE FORMS SET STATUS = 'PUBLISHED', PUBLISHED_AT = SYSTIMESTAMP, UPDATED_AT = SYSTIMESTAMP WHERE ID = :id",
            ['id' => $id]
        );
    }

    public function unpublish(int $id): void
    {
        $this->db->execute(
            "UPDATE FORMS SET STATUS = 'DRAFT', UPDATED_AT = SYSTIMESTAMP WHERE ID = :id",
            ['id' => $id]
        );
    }

    public function close(int $id): void
    {
        $this->db->execute(
            "UPDATE FORMS SET STATUS = 'CLOSED', UPDATED_AT = SYSTIMESTAMP WHERE ID = :id",
            ['id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM FORMS WHERE ID = :id', ['id' => $id]);
    }

    /**
     * 載入完整表單結構（pages, questions, options, rules, audiences）。
     *
     * @return array<string,mixed>|null
     */
    public function loadFull(int $id): ?array
    {
        $form = $this->find($id);
        if (!$form) return null;
        $pages = $this->db->fetchAll(
            'SELECT * FROM FORM_PAGES WHERE FORM_ID = :id ORDER BY SORT_NO, ID',
            ['id' => $id]
        );
        $questions = $this->db->fetchAll(
            'SELECT * FROM QUESTIONS WHERE FORM_ID = :id ORDER BY SORT_NO, ID',
            ['id' => $id]
        );
        $qIds = array_column($questions, 'id');
        $options = [];
        $rules = [];
        if ($qIds) {
            $in = implode(',', array_map('intval', $qIds));
            $options = $this->db->fetchAll(
                "SELECT * FROM QUESTION_OPTIONS WHERE QUESTION_ID IN ({$in}) ORDER BY QUESTION_ID, SORT_NO, ID"
            );
            $rules = $this->db->fetchAll(
                "SELECT * FROM QUESTION_RULES WHERE QUESTION_ID IN ({$in})"
            );
        }
        $aud = $this->db->fetchAll(
            'SELECT * FROM FORM_AUDIENCES WHERE FORM_ID = :id',
            ['id' => $id]
        );
        $form['pages']      = $pages;
        $form['questions']  = $questions;
        $form['options']    = $options;
        $form['rules']      = $rules;
        $form['audiences']  = $aud;
        return $form;
    }

    public function duplicate(int $id, int $userId): int
    {
        return $this->db->transaction(function ($db) use ($id, $userId) {
            $orig = $this->loadFull($id);
            if (!$orig) throw new \RuntimeException('Form not found');
            $newId = $db->insertReturningId(
                "INSERT INTO FORMS (TITLE, DESCRIPTION, AUDIENCE_MODE, REQUIRE_LOGIN, ALLOW_MULTI,
                                    LOCALE_DEFAULT, CREATED_BY, STATUS)
                 VALUES (:t, :d, :am, :rl, :al, :ld, :u, 'DRAFT')
                 RETURNING ID INTO :new_id",
                [
                    't'  => $orig['title'] . ' (Copy)',
                    'd'  => ['value' => (string)($orig['description'] ?? ''), 'type' => SQLT_CHR, 'length' => -1],
                    'am' => $orig['audience_mode'],
                    'rl' => $orig['require_login'],
                    'al' => $orig['allow_multi'],
                    'ld' => $orig['locale_default'],
                    'u'  => $userId,
                ]
            );
            // pages mapping
            $pageMap = [];
            foreach ($orig['pages'] as $p) {
                $pid = $db->insertReturningId(
                    'INSERT INTO FORM_PAGES (FORM_ID, SORT_NO, TITLE) VALUES (:f, :s, :t) RETURNING ID INTO :new_id',
                    ['f' => $newId, 's' => (int)$p['sort_no'], 't' => $p['title']]
                );
                $pageMap[(int)$p['id']] = $pid;
            }
            // questions mapping
            $qMap = [];
            foreach ($orig['questions'] as $q) {
                $newPageId = isset($qMap) && $q['page_id'] !== null ? ($pageMap[(int)$q['page_id']] ?? null) : null;
                $qid = $db->insertReturningId(
                    'INSERT INTO QUESTIONS (FORM_ID, PAGE_ID, SORT_NO, TYPE, LABEL, HELP_TEXT, REQUIRED, CONFIG_JSON)
                     VALUES (:f, :pg, :s, :ty, :lb, :ht, :rq, :cfg)
                     RETURNING ID INTO :new_id',
                    [
                        'f'   => $newId,
                        'pg'  => $newPageId,
                        's'   => (int)$q['sort_no'],
                        'ty'  => $q['type'],
                        'lb'  => $q['label'],
                        'ht'  => $q['help_text'],
                        'rq'  => $q['required'],
                        'cfg' => ['value' => (string)($q['config_json'] ?? ''), 'type' => SQLT_CHR, 'length' => -1],
                    ]
                );
                $qMap[(int)$q['id']] = $qid;
            }
            // options
            $optMap = [];
            foreach ($orig['options'] as $o) {
                if (!isset($qMap[(int)$o['question_id']])) continue;
                $oid = $db->insertReturningId(
                    'INSERT INTO QUESTION_OPTIONS (QUESTION_ID, SORT_NO, LABEL, OPT_VALUE)
                     VALUES (:q, :s, :l, :v) RETURNING ID INTO :new_id',
                    ['q' => $qMap[(int)$o['question_id']], 's' => (int)$o['sort_no'], 'l' => $o['label'], 'v' => $o['opt_value']]
                );
                $optMap[(int)$o['id']] = $oid;
            }
            // rules
            foreach ($orig['rules'] as $r) {
                if (!isset($qMap[(int)$r['question_id']]) || !isset($qMap[(int)$r['source_question_id']])) continue;
                $db->execute(
                    'INSERT INTO QUESTION_RULES (QUESTION_ID, SOURCE_QUESTION_ID, OPERATOR, COMPARE_VALUE, ACTION)
                     VALUES (:q, :s, :op, :cv, :ac)',
                    [
                        'q'  => $qMap[(int)$r['question_id']],
                        's'  => $qMap[(int)$r['source_question_id']],
                        'op' => $r['operator'],
                        'cv' => $r['compare_value'],
                        'ac' => $r['action'],
                    ]
                );
            }
            foreach ($orig['audiences'] as $a) {
                $db->execute(
                    'INSERT INTO FORM_AUDIENCES (FORM_ID, PRINCIPAL_TYPE, PRINCIPAL_VALUE) VALUES (:f, :t, :v)',
                    ['f' => $newId, 't' => $a['principal_type'], 'v' => $a['principal_value']]
                );
            }
            return $newId;
        });
    }
}
