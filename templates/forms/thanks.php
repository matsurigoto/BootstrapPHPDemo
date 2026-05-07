<?php /** @var bool $isPreview */ ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card text-center shadow-sm">
            <div class="card-body py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h4 class="mt-3"><?= $e($tr('form.fill.thanks')) ?></h4>
                <?php if (!empty($isPreview)): ?>
                    <p class="text-muted"><?= $e($tr('form.preview.title')) ?></p>
                <?php endif; ?>
                <a href="<?= $e($url('forms')) ?>" class="btn btn-outline-primary mt-3">
                    <i class="bi bi-list"></i> <?= $e($tr('nav.forms')) ?>
                </a>
            </div>
        </div>
    </div>
</div>
