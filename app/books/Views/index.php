<?php
$pageTitle = 'Books';

$listUrl = \Core\View::url('book');

$pageLink = static function (int $targetPage) use ($listUrl, $q): string {
    $params = ['page' => $targetPage];
    if ($q !== '') {
        $params = ['q' => $q, 'page' => $targetPage];
    }

    return $listUrl . '?' . http_build_query($params);
};
?>
<h1>Books</h1>

<form action="<?= \Core\View::e($listUrl) ?>" method="get">
    <input type="search" name="q" value="<?= \Core\View::e($q) ?>">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
        <a class="clear" href="<?= \Core\View::e($listUrl) ?>">Clear</a>
    <?php endif; ?>
</form>

<p>
    <?php if ($q !== ''): ?>
        <?= \Core\View::e((string) (int) $total) ?>
        results for “<?= \Core\View::e($q) ?>”
    <?php else: ?>
        <?= \Core\View::e((string) (int) $total) ?> books
    <?php endif; ?>
</p>

<?php if ($books === []): ?>
    <p>No books found.</p>
    <p>Change the search and try again.</p>
<?php else: ?>
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
                $showParams = ['id' => (int) ($book['id'] ?? 0)];
                if ($q !== '') {
                    $showParams['q'] = $q;
                }
                $showParams['page'] = (int) $page;
                $showUrl = \Core\View::url('book/show') . '?' . http_build_query($showParams);
                ?>
                <tr>
                    <td><?= \Core\View::e((string) ($book['inventory_number'] ?? '')) ?></td>
                    <td><a href="<?= \Core\View::e($showUrl) ?>"><?= \Core\View::e((string) ($book['title'] ?? '')) ?></a></td>
                    <td><?= \Core\View::e((string) ($book['author'] ?? '')) ?></td>
                    <td><?= \Core\View::e($notes) ?></td>
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
            <a href="<?= \Core\View::e($pageLink((int) $page - 1)) ?>">Previous</a>
        <?php else: ?>
            <span>Previous</span>
        <?php endif; ?>

        <?php $previousNumber = 0; ?>
        <?php foreach ($pageNumbers as $pageNumber): ?>
            <?php if ($previousNumber > 0 && $pageNumber > $previousNumber + 1): ?>
                <span>...</span>
            <?php endif; ?>
            <?php if ($pageNumber === (int) $page): ?>
                <span><?= \Core\View::e((string) (int) $pageNumber) ?></span>
            <?php else: ?>
                <a href="<?= \Core\View::e($pageLink($pageNumber)) ?>"><?= \Core\View::e((string) (int) $pageNumber) ?></a>
            <?php endif; ?>
            <?php $previousNumber = $pageNumber; ?>
        <?php endforeach; ?>

        <?php if ((int) $page < (int) $total_pages): ?>
            <a href="<?= \Core\View::e($pageLink((int) $page + 1)) ?>">Next</a>
        <?php else: ?>
            <span>Next</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>
