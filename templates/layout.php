<?php
/** @var array<string,mixed> $_user */
/** @var string $_locale */
/** @var array<int,string> $_locales */
/** @var string $_base */
/** @var string $_app_name */
/** @var string|null $_flash_success */
/** @var string|null $_flash_error */
/** @var string $_content */
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? $_app_name;
?><!DOCTYPE html>
<html lang="<?= $e($_locale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($pageTitle) ?> · <?= $e($_app_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $e($url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $e($url('forms')) ?>">
            <i class="bi bi-ui-checks"></i> <?= $e($_app_name) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div id="nav" class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <?php if (!empty($_user)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $e($url('forms')) ?>"><i class="bi bi-list-ul"></i> <?= $e($tr('nav.forms')) ?></a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
                        <i class="bi bi-translate"></i> <?= $e($_locale) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($_locales as $loc): ?>
                            <li>
                                <form method="post" action="<?= $e($url('locale')) ?>" class="d-inline">
                                    <?= $csrfField() ?>
                                    <input type="hidden" name="locale" value="<?= $e($loc) ?>">
                                    <button class="dropdown-item <?= $loc === $_locale ? 'active' : '' ?>"><?= $e($loc) ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php if (!empty($_user)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
                            <i class="bi bi-person-circle"></i>
                            <?= $e($_user['display_name'] ?? $_user['username']) ?>
                            <?php if (($_user['role'] ?? '') === 'ADMIN'): ?>
                                <span class="badge bg-warning text-dark">ADMIN</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form method="post" action="<?= $e($url('logout')) ?>">
                                    <?= $csrfField() ?>
                                    <button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> <?= $e($tr('nav.logout')) ?></button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $e($url('login')) ?>"><?= $e($tr('nav.login')) ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container-fluid pb-5">
    <?php if (!empty($_flash_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $e($_flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_flash_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $e($_flash_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?= $_content ?? '' ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
