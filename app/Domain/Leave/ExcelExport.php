<?php

namespace App\Domain\Leave;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

final class ExcelExport
{
    /** @param iterable<list<string|int>> $rows */
    public function download(string $sheetName, string $filename, array $headings, iterable $rows): Response
    {
        $sheet = new Spreadsheet;
        $page = $sheet->getActiveSheet();
        $page->setTitle($sheetName);
        $allRows = (function () use ($headings, $rows) {
            yield $headings;
            yield from $rows;
        })();
        $row = 1;
        foreach ($allRows as $values) {
            foreach ($values as $column => $value) {
                $type = is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                $page->setCellValueExplicit(chr(65 + $column).$row, $value, $type);
            }
            $row++;
        }
        ob_start();
        try {
            (new Xlsx($sheet))->save('php://output');
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
            $sheet->disconnectWorksheets();
        }
        if ($content === false) {
            throw new \RuntimeException('Pembuatan Excel gagal.');
        }

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
