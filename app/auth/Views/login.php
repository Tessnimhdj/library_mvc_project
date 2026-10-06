<?php
$pageTitle = 'Staff login';
$pageHead = (string) ($recaptchaScript ?? '');
?>
<h1>Staff login</h1>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger">
        <?= \Core\View::e($errorMsg) ?>
    </div>
<?php endif; ?>

<form action="<?= \Core\View::e(\Core\View::url('auth/login')) ?>" method="post">
    <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" maxlength="64" required>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>

    <div class="captcha-box">
        <?= $recaptchaWidget ?>
    </div>

    <button type="submit">Sign in</button>
</form>
