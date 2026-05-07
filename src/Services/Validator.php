<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 依題型與 config_json 驗證填答資料。
 * 同時負責伺服器端的條件規則判斷（避免前端隱藏被偽造）。
 */
class Validator
{
    /**
     * @param array<int,array<string,mixed>> $questions  loadFull 取得的 questions
     * @param array<int,array<string,mixed>> $options    所有 options（含 question_id）
     * @param array<int,array<string,mixed>> $rules      所有 rules
     * @param array<string,mixed> $answers               key: question_id (string)，value: 提交值
     * @param array<int,array{name:string,size:int,tmp_name:string,type:string,error:int}> $files key: question_id，多檔可擴充
     * @return array{errors:array<int,string>, visible:array<int,bool>, normalized:array<int,array<string,mixed>>}
     */
    public function validate(array $questions, array $options, array $rules, array $answers, array $files = []): array
    {
        // 建立索引
        $qById = [];
        foreach ($questions as $q) $qById[(int)$q['id']] = $q;
        $optsByQ = [];
        foreach ($options as $o) $optsByQ[(int)$o['question_id']][] = $o;
        $rulesByQ = [];
        foreach ($rules as $r) $rulesByQ[(int)$r['question_id']][] = $r;

        $visible = [];
        foreach ($qById as $qid => $q) {
            $visible[$qid] = true;
        }
        // 評估規則 (簡化：規則以 source 答案 → target 顯示/隱藏)
        foreach ($qById as $qid => $q) {
            $rs = $rulesByQ[$qid] ?? [];
            if (!$rs) continue;
            $shouldShow = null; // null=未表態，true/false 由規則決定
            foreach ($rs as $r) {
                $src = (int)$r['source_question_id'];
                $srcAnswer = $answers[$src] ?? null;
                $srcOpts = $this->normalizeAnswerForCompare($qById[$src] ?? null, $srcAnswer, $optsByQ[$src] ?? []);
                $match = $this->ruleMatches($r['operator'], $r['compare_value'], $srcOpts);
                $action = strtoupper($r['action']);
                if ($action === 'SHOW') {
                    if ($match) { $shouldShow = true; break; }
                    $shouldShow = $shouldShow ?? false;
                } else { // HIDE
                    if ($match) { $shouldShow = false; break; }
                    $shouldShow = $shouldShow ?? true;
                }
            }
            if ($shouldShow !== null) $visible[$qid] = $shouldShow;
        }

        $errors = [];
        $normalized = [];
        foreach ($qById as $qid => $q) {
            if (!$visible[$qid]) continue; // 隱藏題不驗證
            $type = $q['type'];
            $required = ($q['required'] === 'Y');
            $cfg = !empty($q['config_json']) ? (json_decode((string)$q['config_json'], true) ?: []) : [];
            $val = $answers[$qid] ?? null;
            $norm = ['type' => $type];

            switch ($type) {
                case 'TEXT':
                case 'TEXTAREA':
                    $s = is_string($val) ? trim($val) : '';
                    if ($required && $s === '') { $errors[$qid] = 'required'; break; }
                    $max = (int)($cfg['max'] ?? 0);
                    if ($max > 0 && mb_strlen($s) > $max) { $errors[$qid] = 'too_long'; break; }
                    $norm['value_text'] = $s;
                    break;
                case 'SELECT':
                case 'RADIO':
                    $optId = is_array($val) ? ($val[0] ?? null) : $val;
                    $optId = is_numeric($optId) ? (int)$optId : null;
                    $valid = $optId && $this->optionExists($optsByQ[$qid] ?? [], $optId);
                    if ($required && !$valid) { $errors[$qid] = 'required'; break; }
                    if ($valid) $norm['option_ids'] = [$optId];
                    break;
                case 'CHECKBOX':
                    $arr = is_array($val) ? $val : ($val !== null && $val !== '' ? [$val] : []);
                    $valids = [];
                    foreach ($arr as $oid) {
                        if (is_numeric($oid) && $this->optionExists($optsByQ[$qid] ?? [], (int)$oid)) {
                            $valids[] = (int)$oid;
                        }
                    }
                    $valids = array_values(array_unique($valids));
                    $min = (int)($cfg['min'] ?? 0);
                    $max = (int)($cfg['max'] ?? 0);
                    if ($required && !$valids) { $errors[$qid] = 'required'; break; }
                    if ($min > 0 && count($valids) < $min) { $errors[$qid] = 'too_few'; break; }
                    if ($max > 0 && count($valids) > $max) { $errors[$qid] = 'too_many'; break; }
                    $norm['option_ids'] = $valids;
                    break;
                case 'DATE':
                    $s = is_string($val) ? trim($val) : '';
                    if ($required && $s === '') { $errors[$qid] = 'required'; break; }
                    if ($s !== '') {
                        $ts = strtotime($s);
                        if ($ts === false) { $errors[$qid] = 'invalid'; break; }
                        $norm['value_date'] = date('Y-m-d H:i:s', $ts);
                    }
                    break;
                case 'NUMBER':
                    if ($val === null || $val === '') {
                        if ($required) { $errors[$qid] = 'required'; }
                        break;
                    }
                    if (!is_numeric($val)) { $errors[$qid] = 'invalid'; break; }
                    $n = (float)$val;
                    if (isset($cfg['min']) && $cfg['min'] !== '' && $n < (float)$cfg['min']) { $errors[$qid] = 'too_small'; break; }
                    if (isset($cfg['max']) && $cfg['max'] !== '' && $n > (float)$cfg['max']) { $errors[$qid] = 'too_big'; break; }
                    $norm['value_number'] = $n;
                    break;
                case 'RATING':
                    $stars = (int)($cfg['stars'] ?? 5);
                    $n = is_numeric($val) ? (int)$val : 0;
                    if ($required && $n <= 0) { $errors[$qid] = 'required'; break; }
                    if ($n < 0 || $n > $stars) { $errors[$qid] = 'invalid'; break; }
                    if ($n > 0) $norm['value_number'] = $n;
                    break;
                case 'FILE':
                    $f = $files[$qid] ?? null;
                    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        if ($required) $errors[$qid] = 'required';
                        break;
                    }
                    if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) { $errors[$qid] = 'upload_error'; break; }
                    $norm['file'] = $f;
                    break;
                default:
                    $errors[$qid] = 'unknown_type';
            }
            if (!isset($errors[$qid])) {
                $normalized[$qid] = $norm;
            }
        }

        return ['errors' => $errors, 'visible' => $visible, 'normalized' => $normalized];
    }

    /**
     * @param array<int,array<string,mixed>> $opts
     */
    private function optionExists(array $opts, int $optionId): bool
    {
        foreach ($opts as $o) {
            if ((int)$o['id'] === $optionId) return true;
        }
        return false;
    }

    /**
     * 將 source question 答案轉換為比對用的字串清單（涵蓋選項 OPT_VALUE 與文字）。
     * @return array<int,string>
     */
    private function normalizeAnswerForCompare(?array $q, $val, array $opts): array
    {
        if ($q === null) return [];
        $type = $q['type'];
        if (in_array($type, ['SELECT','RADIO','CHECKBOX'], true)) {
            $ids = is_array($val) ? $val : ($val !== null && $val !== '' ? [$val] : []);
            $vals = [];
            foreach ($opts as $o) {
                if (in_array((string)$o['id'], array_map('strval', $ids), true)) {
                    $vals[] = (string)($o['opt_value'] ?? $o['label']);
                }
            }
            return $vals;
        }
        if (is_array($val)) return array_map('strval', $val);
        return [(string)($val ?? '')];
    }

    /**
     * @param array<int,string> $sources
     */
    private function ruleMatches(string $op, ?string $compare, array $sources): bool
    {
        $compare = (string)($compare ?? '');
        switch (strtoupper($op)) {
            case 'EQ':       return in_array($compare, $sources, true);
            case 'NEQ':      return !in_array($compare, $sources, true);
            case 'IN':
                $list = array_map('trim', explode(',', $compare));
                foreach ($sources as $s) if (in_array($s, $list, true)) return true;
                return false;
            case 'CONTAINS':
                foreach ($sources as $s) if (mb_stripos($s, $compare) !== false) return true;
                return false;
            case 'GT':
                foreach ($sources as $s) if (is_numeric($s) && (float)$s > (float)$compare) return true;
                return false;
            case 'LT':
                foreach ($sources as $s) if (is_numeric($s) && (float)$s < (float)$compare) return true;
                return false;
        }
        return false;
    }
}
