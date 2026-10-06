<?php
$loggedIn = \Services\Auth\AuthService::check();
$staffUser = $loggedIn ? \Services\Auth\AuthService::user() : null;
$documentTitle = $title !== '' ? \Core\View::e($title) . ' · Library' : 'Library';
$booksActive = $activeNav === 'books';
$uploadActive = $activeNav === 'upload';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $documentTitle ?></title>
    <link rel="stylesheet" href="<?= \Core\View::e(\Core\View::url('Public/assets/css/app.css')) ?>">
    <?= $pageHead ?>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="<?= \Core\View::e(\Core\View::url('book')) ?>">Library</a>
        <nav class="site-nav">
            <a href="<?= \Core\View::e(\Core\View::url('book')) ?>"<?= $booksActive ? ' class="active" aria-current="page"' : '' ?>>Books</a>
            <a href="<?= \Core\View::e(\Core\View::url('upload')) ?>"<?= $uploadActive ? ' class="active" aria-current="page"' : '' ?>>Import</a>
            <?php if ($loggedIn && is_array($staffUser)): ?>
                <span class="signed-in">Signed in as <?= \Core\View::e((string) ($staffUser['username'] ?? '')) ?></span>
                <form method="post" action="<?= \Core\View::e(\Core\View::url('auth/logout')) ?>">
                    <button type="submit">Sign out</button>
                </form>
            <?php endif; ?>
        </nav>
    </header>
    <main>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <p><?= \Core\View::e((string) date('Y')) ?> Library</p>
    </footer>
</body>
</html>
