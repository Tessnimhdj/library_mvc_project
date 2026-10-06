<?php
$pageTitle = 'Import Excel Data';
$pageHead = (string) ($recaptchaScript ?? '');
?>
<h1>Import Excel Data to MySQL</h1>

<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success">
        <?= \Core\View::e($successMsg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger">
        <?= \Core\View::e($errorMsg) ?>
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
    <div class="alert alert-info report-counts">
        <span><strong>Added:</strong> <?= \Core\View::e((string) $addedCount) ?></span>
        <span><strong>Skipped (duplicates):</strong> <?= \Core\View::e((string) $skippedCount) ?></span>
        <span><strong>Failed:</strong> <?= \Core\View::e((string) $failedCount) ?></span>
    </div>

    <?php if ($failedCount > 0): ?>
        <table>
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
                        <td><?= \Core\View::e((string) (int) ($failure['row'] ?? 0)) ?></td>
                        <td><?= \Core\View::e((string) ($failure['reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($failedCount > $shownFailed): ?>
            <p>
                Showing the first <?= \Core\View::e((string) (int) $shownFailed) ?>
                of <?= \Core\View::e((string) (int) $failedCount) ?> failed rows.
            </p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<form action="<?= \Core\View::e(\Core\View::url('upload/import')) ?>" method="post" enctype="multipart/form-data">
    <div class="field">
        <label for="input_file">Choose file</label>
        <input type="file" id="input_file" name="input_file" accept=".xls,.xlsx">
    </div>

    <div class="captcha-box">
        <?= $recaptchaWidget ?>
    </div>

    <button type="submit">Import</button>
</form>
