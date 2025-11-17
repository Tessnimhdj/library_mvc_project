<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Excel Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <h1>Import Excel Data to MySQL</h1>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-info" style="color:red; margin:10px 0;">
                <?= htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <form action="import" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="input_file" class="form-label">Choose file</label>
                <input type="file" name="input_file" class="form-control" accept=".xls,.xlsx">
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
        </form>
    </div>
</body>

</html>