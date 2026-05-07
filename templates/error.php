<?php /** @var int $status @var string $msg */ ?>
<div class="container py-5 text-center">
    <h1 class="display-1 text-muted"><?= $e($status) ?></h1>
    <p class="lead"><?= $e($msg) ?></p>
    <a class="btn btn-primary" href="<?= $e($url('forms')) ?>">Home</a>
</div>
