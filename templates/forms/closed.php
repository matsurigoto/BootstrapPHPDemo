<?php /** @var string $reason */ ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card text-center shadow-sm">
            <div class="card-body py-5">
                <i class="bi bi-lock-fill text-secondary" style="font-size: 3rem;"></i>
                <h4 class="mt-3"><?= $e($tr('form.fill.unavailable')) ?></h4>
                <p class="text-muted">
                    <?php
                    $msgs = [
                        'not_published' => $tr('form.fill.unavailable'),
                        'not_started'   => $tr('form.fill.not_started'),
                        'ended'         => $tr('form.fill.ended'),
                        'login_required'=> $tr('form.fill.login_required'),
                        'no_permission' => $tr('form.fill.no_permission'),
                        'already_submitted' => $tr('form.fill.already_submitted'),
                    ];
                    echo $e($msgs[$reason] ?? $reason);
                    ?>
                </p>
                <a href="<?= $e($url('forms')) ?>" class="btn btn-outline-secondary mt-2"><i class="bi bi-arrow-left"></i> <?= $e($tr('common.back')) ?></a>
            </div>
        </div>
    </div>
</div>
