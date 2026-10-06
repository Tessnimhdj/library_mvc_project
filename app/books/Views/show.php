<?php
$listParams = [];
if ($q !== '') {
    $listParams['q'] = $q;
}
if ((int) $page > 1) {
    $listParams['page'] = (int) $page;
}
$backUrl = \Router::url('/book');
if ($listParams !== []) {
    $backUrl .= '?' . http_build_query($listParams);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(is_array($book) ? (string) ($book['title'] ?? 'Book') : 'Book', ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; color: #212529; margin: 0; }
        .container { max-width: 960px; margin: 24px auto; padding: 24px; background: #fff; }
        h1 { font-size: 2rem; margin-top: 0; }
        .alert { margin: 10px 0; padding: 12px 16px; border-radius: 4px; background: #f8d7da; color: #842029; }
        dl { margin: 16px 0; }
        dt { font-weight: bold; margin-top: 12px; }
        dd { margin: 4px 0 0; }
        .muted { color: #6c757d; }
        a { color: #0d6efd; }
    </style>
</head>

<body>
    <div class="container">
        <?php if ($error_msg !== ''): ?>
            <div class="alert" style="margin:10px 0;">
                <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php elseif (!is_array($book)): ?>
            <h1>Book not found.</h1>
        <?php else: ?>
            <h1><?= htmlspecialchars((string) ($book['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <dl>
                <dt>Inventory no.</dt>
                <dd><?= htmlspecialchars((string) ($book['inventory_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                <dt>Author</dt>
                <dd>
                    <?php if (trim((string) ($book['author'] ?? '')) === ''): ?>
                        <span class="muted">—</span>
                    <?php else: ?>
                        <?= htmlspecialchars((string) $book['author'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </dd>
                <dt>Notes</dt>
                <dd>
                    <?php if (trim((string) ($book['notes'] ?? '')) === ''): ?>
                        <span class="muted">—</span>
                    <?php else: ?>
                        <?= nl2br(htmlspecialchars((string) $book['notes'], ENT_QUOTES, 'UTF-8')); ?>
                    <?php endif; ?>
                </dd>
            </dl>
        <?php endif; ?>

        <p><a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>">Back to list</a></p>
    </div>
</body>

</html>
