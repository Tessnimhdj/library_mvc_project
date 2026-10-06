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

        <?php if (isset($import_report) && is_array($import_report)): ?>
            <?php
            $addedCount = (int) ($import_report['added'] ?? 0);
            $skippedCount = (int) ($import_report['skipped'] ?? 0);
            $failedCount = (int) ($import_report['failed_count'] ?? 0);
            $failedRows = (isset($import_report['failed']) && is_array($import_report['failed']))
                ? $import_report['failed']
                : [];
            $shownFailed = count($failedRows);
            ?>
            <div class="alert alert-info" style="margin:10px 0;">
                <span style="margin-right:16px;"><strong>Added:</strong> <?= htmlspecialchars((string) $addedCount, ENT_QUOTES, 'UTF-8'); ?></span>
                <span style="margin-right:16px;"><strong>Skipped (duplicates):</strong> <?= htmlspecialchars((string) $skippedCount, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><strong>Failed:</strong> <?= htmlspecialchars((string) $failedCount, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <?php if ($failedCount > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Excel row</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failedRows as $failure): ?>
                            <?php if (!is_array($failure)) { continue; } ?>
                            <tr>
                                <td><?= htmlspecialchars((string) (int) ($failure['row'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($failure['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($failedCount > $shownFailed): ?>
                    <p style="margin:10px 0;">
                        Showing the first <?= htmlspecialchars((string) (int) $shownFailed, ENT_QUOTES, 'UTF-8'); ?>
                        of <?= htmlspecialchars((string) (int) $failedCount, ENT_QUOTES, 'UTF-8'); ?> failed rows.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
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
