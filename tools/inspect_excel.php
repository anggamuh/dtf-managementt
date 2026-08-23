<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__ . '/../DTF EPUL PRINTINGN JAWA.xlsx';

if (! file_exists($path)) {
    echo "Excel file not found: {$path}\n";
    exit(1);
}

echo "Reading workbook: {$path}\n\n";

$reader = IOFactory::createReaderForFile($path);
$spreadsheet = $reader->load($path);

foreach ($spreadsheet->getAllSheets() as $sheet) {
    $title = $sheet->getTitle();
    echo "=== Sheet: {$title} ===\n";

    $highestRow = min($sheet->getHighestRow(), 60); // limit to first 60 rows
    $highestColumn = $sheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    $maxCol = min($highestColumnIndex, 12); // A..L

    for ($r = 1; $r <= $highestRow; $r++) {
        $cells = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
            $cell = $sheet->getCell($addr);
            $val = $cell->getValue();
            $formula = $cell->getValue();
            if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                $formula = 'FORMULA: ' . $cell->getValue();
                try {
                    $calc = $cell->getCalculatedValue();
                    $cells[] = "{$addr}=" . (string)$calc . "(" . $formula . ")";
                    continue;
                } catch (Exception $e) {
                    $cells[] = "{$addr}=[error-eval]({$formula})";
                    continue;
                }
            }

            // normalize long values
            $str = is_null($val) ? '' : (string)$val;
            $str = mb_strimwidth($str, 0, 120, '');
            $cells[] = "{$addr}={$str}";
        }

        echo implode(' | ', $cells) . "\n";
    }

    echo "\n";
}

echo "Done.\n";
