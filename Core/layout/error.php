<?php
$pageTitle = (string) $errorMessage;
?>
<section class="error-block">
    <p class="error-code"><?= \Core\View::e((string) $errorStatus) ?></p>
    <h1><?= \Core\View::e((string) $errorMessage) ?></h1>
    <a class="error-link" href="<?= \Core\View::e(\Core\View::url('book')) ?>">Back to books</a>
</section>
