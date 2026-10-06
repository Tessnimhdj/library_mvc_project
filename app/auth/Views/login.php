<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= $recaptchaScript ?>
</head>

<body>
    <div class="container">
        <h1>Staff login</h1>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" style="margin:10px 0;">
                <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" autocomplete="username" maxlength="64" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
            </div>

            <div style="display: flex; justify-content: center; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <?= $recaptchaWidget ?>
            </div>

            <button type="submit" class="btn btn-primary">Sign in</button>
        </form>
    </div>
</body>

</html>
