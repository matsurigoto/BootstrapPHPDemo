<?php
/** @var array<string,mixed> $form */
$formId = (int)$form['id'];
$initial = [
    'id'              => $formId,
    'title'           => $form['title'],
    'description'     => $form['description'] ?? '',
    'status'          => $form['status'],
    'audience_mode'   => $form['audience_mode'],
    'require_login'   => $form['require_login'],
    'allow_multi'     => $form['allow_multi'],
    'start_at'        => $form['start_at'],
    'end_at'          => $form['end_at'],
    'locale_default'  => $form['locale_default'],
    'pages'           => $form['pages'] ?? [],
    'questions'       => array_map(function ($q) use ($form) {
        $opts = array_values(array_filter($form['options'] ?? [], fn($o) => (int)$o['question_id'] === (int)$q['id']));
        $rules = array_values(array_filter($form['rules'] ?? [], fn($r) => (int)$r['question_id'] === (int)$q['id']));
        $cfg = !empty($q['config_json']) ? (json_decode((string)$q['config_json'], true) ?: []) : [];
        return $q + ['options' => $opts, 'rules' => $rules, 'config' => $cfg];
    }, $form['questions'] ?? []),
    'audiences'       => $form['audiences'] ?? [],
];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <div>
        <a href="<?= $e($url('forms')) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i></a>
        <h4 class="d-inline ms-2 mb-0">
            <i class="bi bi-pencil-square"></i> <span id="form-title-display"><?= $e($form['title']) ?></span>
            <?php
                $st = $form['status'];
                $badge = ['DRAFT' => 'secondary', 'PUBLISHED' => 'success', 'CLOSED' => 'dark'][$st] ?? 'secondary';
            ?>
            <span id="status-badge" class="badge bg-<?= $badge ?> ms-2"><?= $e($tr('form.status.' . $st)) ?></span>
        </h4>
    </div>
    <div class="btn-group">
        <button id="btn-save" class="btn btn-primary">
            <i class="bi bi-save"></i> <?= $e($tr('common.save')) ?>
        </button>
        <a class="btn btn-outline-secondary" target="_blank" href="<?= $e($url('forms/' . $formId . '/preview')) ?>">
            <i class="bi bi-eye"></i> <?= $e($tr('common.preview')) ?>
        </a>
        <?php if ($form['status'] === 'DRAFT'): ?>
            <form method="post" action="<?= $e($url('forms/' . $formId . '/publish')) ?>" class="d-inline" onsubmit="return document.getElementById('btn-save').click() || true">
                <?= $csrfField() ?>
                <button class="btn btn-success"><i class="bi bi-rocket"></i> <?= $e($tr('common.publish')) ?></button>
            </form>
        <?php elseif ($form['status'] === 'PUBLISHED'): ?>
            <form method="post" action="<?= $e($url('forms/' . $formId . '/unpublish')) ?>" class="d-inline">
                <?= $csrfField() ?>
                <button class="btn btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i> <?= $e($tr('common.unpublish')) ?></button>
            </form>
            <form method="post" action="<?= $e($url('forms/' . $formId . '/close')) ?>" class="d-inline" onsubmit="return confirm('?')">
                <?= $csrfField() ?>
                <button class="btn btn-outline-dark"><i class="bi bi-lock"></i> Close</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-info" href="<?= $e($url('forms/' . $formId . '/responses')) ?>">
            <i class="bi bi-bar-chart"></i> <?= $e($tr('nav.responses')) ?>
        </a>
    </div>
</div>

<ul class="nav nav-tabs" id="builder-tabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-build"><i class="bi bi-puzzle"></i> 題目</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-basic"><i class="bi bi-info-circle"></i> 基本</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-audience"><i class="bi bi-people"></i> 對象/期間</a></li>
</ul>
<div class="tab-content border border-top-0 p-3 bg-white">
    <!-- 建構器 -->
    <div class="tab-pane fade show active" id="tab-build">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header"><?= $e($tr('q.add')) ?></div>
                    <div class="list-group list-group-flush" id="q-type-list">
                        <?php foreach (['TEXT','TEXTAREA','SELECT','CHECKBOX','RADIO','DATE','NUMBER','FILE','RATING'] as $type): ?>
                            <button type="button" class="list-group-item list-group-item-action q-add-btn" data-type="<?= $type ?>">
                                <i class="bi bi-plus-circle"></i> <?= $e($tr('q.type.' . $type)) ?>
                            </button>
                        <?php endforeach; ?>
                        <button type="button" id="btn-add-page" class="list-group-item list-group-item-action text-primary">
                            <i class="bi bi-files"></i> <?= $e($tr('q.add_page')) ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div id="builder-canvas"></div>
            </div>
        </div>
    </div>

    <!-- 基本 -->
    <div class="tab-pane fade" id="tab-basic">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label"><?= $e($tr('form.title')) ?></label>
                <input id="f-title" type="text" class="form-control" value="<?= $e($form['title']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Locale</label>
                <select id="f-locale" class="form-select">
                    <?php foreach (['zh-TW','en'] as $loc): ?>
                        <option value="<?= $loc ?>" <?= $form['locale_default'] === $loc ? 'selected' : '' ?>><?= $loc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label"><?= $e($tr('form.description')) ?></label>
                <textarea id="f-desc" class="form-control" rows="4"><?= $e($form['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- 對象/期間 -->
    <div class="tab-pane fade" id="tab-audience">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?= $e($tr('form.audience')) ?></label>
                <select id="f-audience" class="form-select">
                    <?php foreach (['PUBLIC','USER','GROUP'] as $a): ?>
                        <option value="<?= $a ?>" <?= $form['audience_mode'] === $a ? 'selected' : '' ?>><?= $e($tr('form.audience.' . $a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $e($tr('form.require_login')) ?></label>
                <select id="f-login" class="form-select">
                    <option value="N" <?= $form['require_login'] === 'N' ? 'selected' : '' ?>><?= $e($tr('common.no')) ?></option>
                    <option value="Y" <?= $form['require_login'] === 'Y' ? 'selected' : '' ?>><?= $e($tr('common.yes')) ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $e($tr('form.allow_multi')) ?></label>
                <select id="f-multi" class="form-select">
                    <option value="N" <?= $form['allow_multi'] === 'N' ? 'selected' : '' ?>><?= $e($tr('common.no')) ?></option>
                    <option value="Y" <?= $form['allow_multi'] === 'Y' ? 'selected' : '' ?>><?= $e($tr('common.yes')) ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $e($tr('form.start_at')) ?></label>
                <input id="f-start" type="datetime-local" class="form-control"
                       value="<?= $e($form['start_at'] ? date('Y-m-d\TH:i', strtotime((string)$form['start_at'])) : '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $e($tr('form.end_at')) ?></label>
                <input id="f-end" type="datetime-local" class="form-control"
                       value="<?= $e($form['end_at'] ? date('Y-m-d\TH:i', strtotime((string)$form['end_at'])) : '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">指定使用者 / AD 群組（每行一筆，格式 <code>USER:username</code> 或 <code>AD_GROUP:CN=...</code>）</label>
                <textarea id="f-aud-list" class="form-control" rows="4"><?php
                    foreach ($form['audiences'] as $a) {
                        echo $e($a['principal_type'] . ':' . $a['principal_value']) . "\n";
                    }
                ?></textarea>
            </div>
        </div>
    </div>
</div>

<script>
window.FORMHUB_INIT = <?= json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.FORMHUB_CSRF = "<?= $e(\App\Core\Csrf::token()) ?>";
window.FORMHUB_BASE = "<?= $e(rtrim($_base, '/')) ?>";
window.FORMHUB_LANG = {
    'q.label': "<?= $e($tr('q.label')) ?>",
    'q.help_text': "<?= $e($tr('q.help_text')) ?>",
    'q.required': "<?= $e($tr('q.required')) ?>",
    'q.options': "<?= $e($tr('q.options')) ?>",
    'q.add_option': "<?= $e($tr('q.add_option')) ?>",
    'q.rules': "<?= $e($tr('q.rules')) ?>",
    'q.config.min': "<?= $e($tr('q.config.min')) ?>",
    'q.config.max': "<?= $e($tr('q.config.max')) ?>",
    'q.config.placeholder': "<?= $e($tr('q.config.placeholder')) ?>",
    'q.config.stars': "<?= $e($tr('q.config.stars')) ?>",
    'q.config.accept': "<?= $e($tr('q.config.accept')) ?>",
    'common.delete': "<?= $e($tr('common.delete')) ?>",
    'common.save': "<?= $e($tr('common.save')) ?>",
    'common.success': "<?= $e($tr('common.success')) ?>",
    'common.fail': "<?= $e($tr('common.fail')) ?>",
    'q.type.TEXT':"<?= $e($tr('q.type.TEXT')) ?>",
    'q.type.TEXTAREA':"<?= $e($tr('q.type.TEXTAREA')) ?>",
    'q.type.SELECT':"<?= $e($tr('q.type.SELECT')) ?>",
    'q.type.CHECKBOX':"<?= $e($tr('q.type.CHECKBOX')) ?>",
    'q.type.RADIO':"<?= $e($tr('q.type.RADIO')) ?>",
    'q.type.DATE':"<?= $e($tr('q.type.DATE')) ?>",
    'q.type.NUMBER':"<?= $e($tr('q.type.NUMBER')) ?>",
    'q.type.FILE':"<?= $e($tr('q.type.FILE')) ?>",
    'q.type.RATING':"<?= $e($tr('q.type.RATING')) ?>"
};
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="<?= $e($url('assets/js/form-builder.js')) ?>"></script>
