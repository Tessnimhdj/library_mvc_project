<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Excel Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?= $recaptchaScript ?>
</head>

<body>
    <div class="container">
        <h1>Import Excel Data to MySQL</h1>

        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success" style="margin:10px 0;">
                <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" style="margin:10px 0;">
                <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data">

            <div class="mb-3">
                <label for="input_file" class="form-label">Choose file</label>
                <input type="file" id="input_file" name="input_file" class="form-control" accept=".xls,.xlsx">
            </div>

            <div style="display: flex; justify-content: center; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <?= $recaptchaWidget ?>
            </div>

            <button type="submit" class="btn btn-primary">Import</button>
        </form>

    </div>
</body>

</html>
