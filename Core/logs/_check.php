<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
foreach (glob(dirname(__DIR__, 2) . '/Public/Uploads/*.xlsx') as $file) {
    $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet()->toArray();
    foreach ($rows as $index => $row) {
        foreach ($row as $cell) {
            $value = (string) $cell;
            if (preg_match('/\p{Arabic}/u', $value)) {
                echo basename($file) . ' row ' . $index . ': ' . $value . PHP_EOL;
            }
        }
    }
}
$zip = new ZipArchive();
$file = dirname(__DIR__, 2) . '/Public/Uploads/1764495611_book.xlsx';
$zip->open($file);
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    $content = $zip->getFromIndex($i);
    if (preg_match_all('/\p{Arabic}+(?:[^\p{Arabic}<]{0,20}\p{Arabic}+)*/u', $content, $matches)) {
        echo $name . PHP_EOL;
        echo implode("\n", array_unique($matches[0])) . PHP_EOL;
    }
}
$zip->close();
echo "done\n";
