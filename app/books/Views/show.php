<?php
$pageTitle = (string) ($book['title'] ?? '');

$listParams = [];
if ($q !== '') {
    $listParams['q'] = $q;
}
if ((int) $page > 1) {
    $listParams['page'] = (int) $page;
}
$backUrl = \Core\View::url('book');
if ($listParams !== []) {
    $backUrl .= '?' . http_build_query($listParams);
}
?>
<h1><?= \Core\View::e((string) ($book['title'] ?? '')) ?></h1>
<dl>
    <dt>Inventory no.</dt>
    <dd><?= \Core\View::e((string) ($book['inventory_number'] ?? '')) ?></dd>
    <dt>Author</dt>
    <dd>
        <?php if (trim((string) ($book['author'] ?? '')) === ''): ?>
            <span class="muted">—</span>
        <?php else: ?>
            <?= \Core\View::e((string) $book['author']) ?>
        <?php endif; ?>
    </dd>
    <dt>Notes</dt>
    <dd>
        <?php if (trim((string) ($book['notes'] ?? '')) === ''): ?>
            <span class="muted">—</span>
        <?php else: ?>
            <?= nl2br(\Core\View::e((string) $book['notes'])) ?>
        <?php endif; ?>
    </dd>
</dl>
<p><a href="<?= \Core\View::e($backUrl) ?>">Back to list</a></p>
