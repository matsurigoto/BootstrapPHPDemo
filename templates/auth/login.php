<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow">
            <div class="card-body p-4">
                <h4 class="mb-4 text-center"><i class="bi bi-shield-lock"></i> <?= $e($tr('login.title')) ?></h4>
                <form method="post" action="<?= $e($url('login')) ?>">
                    <?= $csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($tr('login.username')) ?></label>
                        <input type="text" name="username" class="form-control" autofocus required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($tr('login.password')) ?></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100"><?= $e($tr('login.submit')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
