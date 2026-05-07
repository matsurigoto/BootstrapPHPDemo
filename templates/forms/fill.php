<?php
/** @var array<string,mixed> $form */
/** @var bool $isPreview */
$optsByQ = [];
foreach ($form['options'] as $o) $optsByQ[(int)$o['question_id']][] = $o;
$rulesByQ = [];
foreach ($form['rules'] as $r) $rulesByQ[(int)$r['question_id']][] = $r;

$action = $isPreview
    ? $url('forms/' . $form['id'] . '/preview')  // 預覽不送 POST 也可，但保留
    : $url('f/' . $form['id']);
?>
<?php if ($isPreview): ?>
<div class="alert alert-warning"><i class="bi bi-eye"></i> <?= $e($tr('form.preview.title')) ?> — 此為預覽，送出不會寫入</div>
<?php endif; ?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="mb-2"><?= $e($form['title']) ?></h3>
                <?php if (!empty($form['description'])): ?>
                    <p class="text-muted" style="white-space: pre-wrap"><?= $e($form['description']) ?></p>
                <?php endif; ?>
                <hr>
                <form method="post" action="<?= $e($url('f/' . $form['id'])) ?>" enctype="multipart/form-data" id="fill-form" novalidate>
                    <?= $csrfField() ?>
                    <?php if ($isPreview): ?><input type="hidden" name="_preview" value="1"><?php endif; ?>
                    <?php foreach ($form['questions'] as $q): ?>
                        <?php
                            $qid = (int)$q['id'];
                            $cfg = !empty($q['config_json']) ? (json_decode((string)$q['config_json'], true) ?: []) : [];
                            $opts = $optsByQ[$qid] ?? [];
                            $rulesJson = json_encode($rulesByQ[$qid] ?? [], JSON_UNESCAPED_UNICODE);
                            $required = $q['required'] === 'Y';
                            $name = 'a[' . $qid . ']';
                        ?>
                        <div class="mb-4 q-block" data-qid="<?= $qid ?>" data-rules='<?= $e($rulesJson) ?>'>
                            <label class="form-label fw-semibold">
                                <?= $e($q['label']) ?>
                                <?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
                            </label>
                            <?php if (!empty($q['help_text'])): ?>
                                <div class="form-text mb-1"><?= $e($q['help_text']) ?></div>
                            <?php endif; ?>
                            <?php switch ($q['type']):
                                case 'TEXT': ?>
                                    <input class="form-control" type="text" name="<?= $name ?>"
                                           placeholder="<?= $e($cfg['placeholder'] ?? '') ?>"
                                           <?= $required ? 'required' : '' ?>
                                           <?= !empty($cfg['max']) ? 'maxlength="' . (int)$cfg['max'] . '"' : '' ?>>
                                    <?php break; case 'TEXTAREA': ?>
                                    <textarea class="form-control" rows="4" name="<?= $name ?>"
                                              placeholder="<?= $e($cfg['placeholder'] ?? '') ?>"
                                              <?= $required ? 'required' : '' ?>></textarea>
                                    <?php break; case 'SELECT': ?>
                                    <select class="form-select" name="<?= $name ?>" <?= $required ? 'required' : '' ?>>
                                        <option value="">— —</option>
                                        <?php foreach ($opts as $o): ?>
                                            <option value="<?= (int)$o['id'] ?>"><?= $e($o['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php break; case 'RADIO': ?>
                                    <?php foreach ($opts as $o): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="<?= $name ?>" value="<?= (int)$o['id'] ?>" id="o<?= $qid ?>_<?= $o['id'] ?>" <?= $required ? 'required' : '' ?>>
                                            <label class="form-check-label" for="o<?= $qid ?>_<?= $o['id'] ?>"><?= $e($o['label']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php break; case 'CHECKBOX': ?>
                                    <?php foreach ($opts as $o): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="<?= $name ?>[]" value="<?= (int)$o['id'] ?>" id="o<?= $qid ?>_<?= $o['id'] ?>">
                                            <label class="form-check-label" for="o<?= $qid ?>_<?= $o['id'] ?>"><?= $e($o['label']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php break; case 'DATE': ?>
                                    <input class="form-control" type="datetime-local" name="<?= $name ?>" <?= $required ? 'required' : '' ?>>
                                    <?php break; case 'NUMBER': ?>
                                    <input class="form-control" type="number" name="<?= $name ?>"
                                           <?= isset($cfg['min']) ? 'min="' . $e($cfg['min']) . '"' : '' ?>
                                           <?= isset($cfg['max']) ? 'max="' . $e($cfg['max']) . '"' : '' ?>
                                           step="any" <?= $required ? 'required' : '' ?>>
                                    <?php break; case 'FILE': ?>
                                    <input class="form-control" type="file" name="<?= $name ?>"
                                           <?= !empty($cfg['accept']) ? 'accept="' . $e($cfg['accept']) . '"' : '' ?>
                                           <?= $required ? 'required' : '' ?>>
                                    <?php break; case 'RATING': ?>
                                    <?php $stars = (int)($cfg['stars'] ?? 5); ?>
                                    <div class="rating-group" data-stars="<?= $stars ?>">
                                        <?php for ($i = 1; $i <= $stars; $i++): ?>
                                            <input type="radio" class="btn-check" name="<?= $name ?>" value="<?= $i ?>" id="r<?= $qid ?>_<?= $i ?>" autocomplete="off" <?= $required && $i === 1 ? 'required' : '' ?>>
                                            <label class="btn btn-outline-warning btn-sm" for="r<?= $qid ?>_<?= $i ?>"><i class="bi bi-star-fill"></i> <?= $i ?></label>
                                        <?php endfor; ?>
                                    </div>
                            <?php break; endswitch; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> <?= $e($tr('common.submit')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= $e($url('assets/js/form-runtime.js')) ?>"></script>
