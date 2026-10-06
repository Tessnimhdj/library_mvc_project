<?php
$listUrl = \Router::url('/book');

$pageLink = static function (int $targetPage) use ($listUrl, $q): string {
    $params = ['page' => $targetPage];
    if ($q !== '') {
        $params = ['q' => $q, 'page' => $targetPage];
    }

    return $listUrl . '?' . http_build_query($params);
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; color: #212529; margin: 0; }
        .container { max-width: 960px; margin: 24px auto; padding: 24px; background: #fff; }
        h1 { font-size: 2rem; margin-top: 0; }
        .alert { margin: 10px 0; padding: 12px 16px; border-radius: 4px; background: #f8d7da; color: #842029; }
        form { margin: 16px 0; }
        input[type="search"] { padding: 6px 12px; border: 1px solid #ced4da; border-radius: 4px; min-width: 240px; }
        button { background: #0d6efd; color: #fff; border: 0; border-radius: 4px; padding: 6px 12px; }
        .clear { margin-left: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #dee2e6; text-align: left; padding: 8px; vertical-align: top; }
        .pager { margin-top: 16px; }
        .pager a, .pager span { margin-right: 8px; }
    </style>
</head>

<body>
    <div class="container">
        <h1>Books</h1>

        <?php if ($error_msg !== ''): ?>
            <div class="alert" style="margin:10px 0;">
                <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?>" method="get">
            <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Search</button>
            <?php if ($q !== ''): ?>
                <a class="clear" href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?>">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($error_msg === ''): ?>
        <p>
            <?php if ($q !== ''): ?>
                <?= htmlspecialchars((string) (int) $total, ENT_QUOTES, 'UTF-8'); ?>
                results for “<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>”
            <?php else: ?>
                <?= htmlspecialchars((string) (int) $total, ENT_QUOTES, 'UTF-8'); ?> books
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <?php if ($error_msg === '' && $books === []): ?>
            <p>No books found.</p>
            <p>Change the search and try again.</p>
        <?php elseif ($error_msg === ''): ?>
            <table>
                <thead>
                    <tr>
                        <th>Inventory no.</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <?php
                        $notes = mb_strimwidth((string) ($book['notes'] ?? ''), 0, 120, '...', 'UTF-8');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($book['inventory_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php
                                $showParams = ['id' => (int) ($book['id'] ?? 0)];
                                if ($q !== '') {
                                    $showParams['q'] = $q;
                                }
                                $showParams['page'] = (int) $page;
                                $showUrl = \Router::url('/book/show') . '?' . http_build_query($showParams);
                            ?><a href="<?= htmlspecialchars($showUrl, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) ($book['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td><?= htmlspecialchars((string) ($book['author'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ((int) $total_pages > 1): ?>
            <?php
            $pageNumbers = [1, (int) $total_pages];
            for ($i = (int) $page - 2; $i <= (int) $page + 2; $i++) {
                if ($i >= 1 && $i <= (int) $total_pages) {
                    $pageNumbers[] = $i;
                }
            }
            $pageNumbers = array_values(array_unique($pageNumbers));
            sort($pageNumbers);
            ?>
            <nav class="pager">
                <?php if ((int) $page > 1): ?>
                    <a href="<?= htmlspecialchars($pageLink((int) $page - 1), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                <?php else: ?>
                    <span>Previous</span>
                <?php endif; ?>

                <?php $previousNumber = 0; ?>
                <?php foreach ($pageNumbers as $pageNumber): ?>
                    <?php if ($previousNumber > 0 && $pageNumber > $previousNumber + 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <?php if ($pageNumber === (int) $page): ?>
                        <span><?= htmlspecialchars((string) (int) $pageNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($pageLink($pageNumber), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) (int) $pageNumber, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endif; ?>
                    <?php $previousNumber = $pageNumber; ?>
                <?php endforeach; ?>

                <?php if ((int) $page < (int) $total_pages): ?>
                    <a href="<?= htmlspecialchars($pageLink((int) $page + 1), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                <?php else: ?>
                    <span>Next</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</body>

</html>
