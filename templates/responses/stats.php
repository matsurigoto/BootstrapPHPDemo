<?php
/** @var array $form */
/** @var array $aggregates */  // each: ['q'=>['id','label','type'], 'kind'=>..., 'data'=>...]
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><?= $e($form['title']) ?></h3>
        <small class="text-muted"><?= $e($tr('resp.stats')) ?></small>
    </div>
    <a href="<?= $e($url('forms/' . $form['id'] . '/responses')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= $e($tr('common.back')) ?></a>
</div>

<div class="row g-3">
    <?php foreach ($aggregates as $i => $agg): $q = $agg['q']; $kind = $agg['kind']; ?>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3"><?= $e($q['label']) ?> <small class="text-muted">(<?= $e($q['type']) ?>)</small></h6>
                    <?php if ($kind === 'options'): ?>
                        <canvas id="chart-<?= $i ?>" height="180"></canvas>
                        <script type="application/json" id="data-<?= $i ?>"><?= json_encode($agg['data'], JSON_UNESCAPED_UNICODE) ?></script>
                    <?php elseif ($kind === 'numeric'): ?>
                        <div class="row text-center">
                            <div class="col"><div class="text-muted small">Avg</div><strong><?= $e((string)round((float)$agg['data']['avg'], 2)) ?></strong></div>
                            <div class="col"><div class="text-muted small">Min</div><strong><?= $e((string)$agg['data']['min']) ?></strong></div>
                            <div class="col"><div class="text-muted small">Max</div><strong><?= $e((string)$agg['data']['max']) ?></strong></div>
                            <div class="col"><div class="text-muted small">Count</div><strong><?= (int)$agg['data']['count'] ?></strong></div>
                        </div>
                    <?php elseif ($kind === 'date'): ?>
                        <canvas id="chart-<?= $i ?>" height="180"></canvas>
                        <script type="application/json" id="data-<?= $i ?>"><?= json_encode(['kind' => 'date', 'series' => $agg['data']], JSON_UNESCAPED_UNICODE) ?></script>
                    <?php elseif ($kind === 'text'): ?>
                        <ul class="list-unstyled small mb-0" style="max-height:200px;overflow:auto;">
                            <?php foreach (($agg['data'] ?? []) as $sample): ?>
                                <li class="border-bottom py-1"><?= nl2br($e((string)$sample)) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($kind === 'file'): ?>
                        <p class="mb-0"><?= (int)$agg['data']['count'] ?> 個檔案</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= $e($url('assets/js/stats.js')) ?>"></script>
