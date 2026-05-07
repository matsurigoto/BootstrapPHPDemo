<?php
/** @var array $form */
/** @var array $response */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $e($form['title']) ?> · #<?= (int)$response['id'] ?></h4>
        <small class="text-muted">
            <?= $e($tr('resp.submitted_at')) ?>: <?= $e($response['submitted_at'] ?? '') ?>
            · <?= $e($tr('resp.user')) ?>:
            <?= !empty($response['user_name']) ? $e($response['user_name']) . ' (' . $e($response['username']) . ')' : '— ' . $e($tr('resp.anonymous')) . ' —' ?>
            · IP: <?= $e($response['ip_addr'] ?? '') ?>
        </small>
    </div>
    <a href="<?= $e($url('forms/' . $form['id'] . '/responses')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= $e($tr('common.back')) ?></a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($response['answers'])): ?>
            <p class="text-muted text-center my-4">—</p>
        <?php else: ?>
            <?php foreach ($response['answers'] as $a): ?>
                <div class="mb-3 pb-2 border-bottom">
                    <div class="fw-semibold"><?= $e($a['q_label']) ?></div>
                    <div class="mt-1">
                        <?php
                        $t = $a['q_type'];
                        $opts = $a['option_labels'] ?? [];
                        if (in_array($t, ['SELECT','RADIO','CHECKBOX'], true)) {
                            if ($opts) {
                                foreach ($opts as $lbl) {
                                    echo '<span class="badge bg-info text-dark me-1">' . $e($lbl) . '</span>';
                                }
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                        } elseif ($t === 'FILE') {
                            if (!empty($a['file_path'])) {
                                $href = $url($a['file_path']);
                                echo '<a href="' . $e($href) . '" target="_blank"><i class="bi bi-paperclip"></i> ' . $e(basename((string)$a['file_path'])) . '</a>';
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                        } elseif ($t === 'NUMBER' || $t === 'RATING') {
                            echo $e((string)($a['value_number'] ?? $a['value_text'] ?? ''));
                        } elseif ($t === 'DATE') {
                            echo $e((string)($a['value_date'] ?? $a['value_text'] ?? ''));
                        } else {
                            echo nl2br($e((string)($a['value_text'] ?? '')));
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
