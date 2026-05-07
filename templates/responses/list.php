<?php
/** @var array $form */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $sort */
/** @var string $dir */
$pages = max(1, (int)ceil($total / $perPage));
$sortLink = function (string $col, string $label) use ($sort, $dir, $url, $form, $e) {
    $newDir = ($sort === $col && strtoupper($dir) === 'ASC') ? 'DESC' : 'ASC';
    $href = $url('forms/' . $form['id'] . '/responses?sort=' . $col . '&dir=' . $newDir);
    $icon = $sort === $col ? (strtoupper($dir) === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '';
    return '<a href="' . $e($href) . '" class="text-decoration-none">' . $e($label) . ' ' . $icon . '</a>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><?= $e($form['title']) ?></h3>
        <small class="text-muted"><?= $e($tr('resp.list.title')) ?> · <?= (int)$total ?></small>
    </div>
    <div class="btn-group">
        <a href="<?= $e($url('forms/' . $form['id'] . '/stats')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-bar-chart"></i> <?= $e($tr('resp.stats')) ?>
        </a>
        <a href="<?= $e($url('forms/' . $form['id'] . '/responses.csv')) ?>" class="btn btn-success">
            <i class="bi bi-download"></i> <?= $e($tr('resp.export.csv')) ?>
        </a>
        <a href="<?= $e($url('forms/' . $form['id'] . '/edit')) ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> <?= $e($tr('common.edit')) ?>
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th><?= $sortLink('id', '#') ?></th>
                <th><?= $sortLink('user', $tr('resp.user')) ?></th>
                <th><?= $e($tr('resp.ip')) ?></th>
                <th><?= $sortLink('submitted_at', $tr('resp.submitted_at')) ?></th>
                <th class="text-end"><?= $e($tr('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">—</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td>
                        <?php if (!empty($row['user_name'])): ?>
                            <?= $e($row['user_name']) ?>
                            <small class="text-muted">(<?= $e($row['username']) ?>)</small>
                        <?php else: ?>
                            <span class="text-muted">— <?= $e($tr('resp.anonymous')) ?> —</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= $e($row['ip_addr'] ?? '') ?></small></td>
                    <td><?= $e($row['submitted_at'] ?? '') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= $e($url('forms/' . $form['id'] . '/responses/' . $row['id'])) ?>">
                            <i class="bi bi-eye"></i>
                        </a>
                        <form method="post" action="<?= $e($url('forms/' . $form['id'] . '/responses/' . $row['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('<?= $e($tr('common.confirm_delete')) ?>');">
                            <?= $csrfField() ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $e($url('forms/' . $form['id'] . '/responses?page=' . $i . '&sort=' . $sort . '&dir=' . $dir)) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
