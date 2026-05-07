<?php
/** @var array<int,array<string,mixed>> $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var ?string $q */
/** @var ?string $status */
/** @var string $sort */
/** @var string $dir */
$totalPages = max(1, (int)ceil($total / $perPage));
$sortLink = function (string $col) use ($sort, $dir, $url, $q, $status): string {
    $newDir = ($sort === $col && strtoupper($dir) === 'ASC') ? 'DESC' : 'ASC';
    $params = http_build_query(array_filter([
        'q' => $q, 'status' => $status, 'sort' => $col, 'dir' => $newDir,
    ], fn($v) => $v !== null && $v !== ''));
    return $url('forms') . '?' . $params;
};
$icon = function (string $col) use ($sort, $dir): string {
    if ($sort !== $col) return '<i class="bi bi-arrow-down-up text-muted small"></i>';
    return strtoupper($dir) === 'ASC'
        ? '<i class="bi bi-caret-up-fill"></i>'
        : '<i class="bi bi-caret-down-fill"></i>';
};
?>
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <h3 class="mb-0"><i class="bi bi-list-ul"></i> <?= $e($tr('form.list.title')) ?></h3>
    <form method="post" action="<?= $e($url('forms')) ?>" class="d-inline">
        <?= $csrfField() ?>
        <input type="hidden" name="title" value="<?= $e($tr('form.list.title')) ?>">
        <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> <?= $e($tr('nav.new_form')) ?></button>
    </form>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-sm-5">
        <input type="text" name="q" class="form-control" value="<?= $e($q ?? '') ?>" placeholder="<?= $e($tr('common.search')) ?>...">
    </div>
    <div class="col-sm-3">
        <select name="status" class="form-select">
            <option value="">— <?= $e($tr('common.filter')) ?> —</option>
            <?php foreach (['DRAFT','PUBLISHED','CLOSED'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $e($tr('form.status.' . $st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-2">
        <button class="btn btn-outline-primary w-100"><?= $e($tr('common.search')) ?></button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><a class="text-decoration-none text-dark" href="<?= $e($sortLink('title')) ?>"><?= $e($tr('form.title')) ?> <?= $icon('title') ?></a></th>
                    <th><a class="text-decoration-none text-dark" href="<?= $e($sortLink('status')) ?>"><?= $e($tr('form.status')) ?> <?= $icon('status') ?></a></th>
                    <th><a class="text-decoration-none text-dark" href="<?= $e($sortLink('start_at')) ?>"><?= $e($tr('form.start_at')) ?> <?= $icon('start_at') ?></a></th>
                    <th><a class="text-decoration-none text-dark" href="<?= $e($sortLink('end_at')) ?>"><?= $e($tr('form.end_at')) ?> <?= $icon('end_at') ?></a></th>
                    <th><a class="text-decoration-none text-dark" href="<?= $e($sortLink('responses')) ?>"><?= $e($tr('form.responses_count')) ?> <?= $icon('responses') ?></a></th>
                    <th><?= $e($tr('form.created_by')) ?></th>
                    <th><a class="text-decoration-none text-dark" href="<?= $e($sortLink('updated_at')) ?>"><?= $e($tr('form.updated_at')) ?> <?= $icon('updated_at') ?></a></th>
                    <th class="text-end"><?= $e($tr('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><?= $e($tr('common.no_data')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                        $st = $row['status'];
                        $badge = ['DRAFT' => 'secondary', 'PUBLISHED' => 'success', 'CLOSED' => 'dark'][$st] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <a href="<?= $e($url('forms/' . $row['id'] . '/edit')) ?>" class="fw-semibold text-decoration-none">
                                <?= $e($row['title']) ?>
                            </a>
                        </td>
                        <td><span class="badge bg-<?= $badge ?>"><?= $e($tr('form.status.' . $st)) ?></span></td>
                        <td><?= $e($row['start_at'] ?? '—') ?></td>
                        <td><?= $e($row['end_at'] ?? '—') ?></td>
                        <td>
                            <a href="<?= $e($url('forms/' . $row['id'] . '/responses')) ?>"
                               class="badge bg-info text-decoration-none"><?= (int)$row['responses_count'] ?></a>
                        </td>
                        <td><?= $e($row['creator_name'] ?? '') ?></td>
                        <td class="text-muted small"><?= $e($row['updated_at'] ?? '') ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-outline-secondary" href="<?= $e($url('forms/' . $row['id'] . '/edit')) ?>" title="<?= $e($tr('common.edit')) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a class="btn btn-outline-secondary" href="<?= $e($url('forms/' . $row['id'] . '/preview')) ?>" target="_blank" title="<?= $e($tr('common.preview')) ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a class="btn btn-outline-secondary" href="<?= $e($url('f/' . $row['id'])) ?>" target="_blank" title="<?= $e($tr('common.view')) ?>">
                                    <i class="bi bi-link-45deg"></i>
                                </a>
                                <form method="post" action="<?= $e($url('forms/' . $row['id'] . '/duplicate')) ?>" class="d-inline">
                                    <?= $csrfField() ?>
                                    <button class="btn btn-outline-secondary" title="<?= $e($tr('common.duplicate')) ?>"><i class="bi bi-files"></i></button>
                                </form>
                                <form method="post" action="<?= $e($url('forms/' . $row['id'] . '/delete')) ?>" class="d-inline"
                                      onsubmit="return confirm('<?= $e($tr('common.confirm_delete')) ?>')">
                                    <?= $csrfField() ?>
                                    <button class="btn btn-outline-danger" title="<?= $e($tr('common.delete')) ?>"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php
            $params = http_build_query(array_filter([
                'q' => $q, 'status' => $status, 'sort' => $sort, 'dir' => $dir, 'page' => $i,
            ], fn($v) => $v !== null && $v !== ''));
            ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $e($url('forms')) ?>?<?= $params ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
