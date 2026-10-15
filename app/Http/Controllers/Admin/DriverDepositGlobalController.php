<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DriverDepositGlobalReportService;
use Gate;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Cell\DataType, Cell\Coordinate, Writer\Xlsx};

class DriverDepositGlobalController extends Controller
{
    public function index(Request $request, DriverDepositGlobalReportService $service)
    {
        abort_if(Gate::denies('driver_deposit_access'), 403);
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'export' => ['nullable', 'in:xlsx'],
        ]);
        $from = $filters['date_from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['date_to'] ?? now()->toDateString();
        if ($from > $to) throw \Illuminate\Validation\ValidationException::withMessages(['date_to' => 'A data final deve ser igual ou posterior à data inicial.']);
        $rows = $service->rows($from, $to);
        $columns = $service::COLUMNS;
        $totals = [];
        foreach (array_slice(array_keys($columns), 3) as $key) $totals[$key] = round($rows->sum($key), 2);
        if (($filters['export'] ?? null) === 'xlsx') {
            return response()->streamDownload(function () use ($rows, $from, $to, $totals) {
                $book = $this->workbook($rows, $from, $to, $totals);
                try { (new Xlsx($book))->save('php://output'); }
                finally { $book->disconnectWorksheets(); }
            }, "caucoes-globais-{$from}-{$to}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        return view('admin.driverDeposits.global', compact('rows', 'columns', 'totals', 'from', 'to'));
    }

    public function workbook($rows, string $from, string $to, array $totals): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Cauções globais');
        $columns = DriverDepositGlobalReportService::COLUMNS;
        $last = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->setCellValue('A1', "Cauções globais — {$from} a {$to}");
        $sheet->mergeCells("A1:{$last}1");
        $sheet->fromArray(array_values($columns), null, 'A3');
        $line = 4;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($columns as $key => $label) {
                $cell = Coordinate::stringFromColumnIndex($col) . $line;
                $sheet->setCellValueExplicit($cell, $row[$key], $col <= 3 ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
                $col++;
            }
            $line++;
        }
        $sheet->setCellValue('B' . $line, 'TOTAL');
        foreach (array_keys($columns) as $index => $key) {
            if ($index >= 3) $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1) . $line, $totals[$key], DataType::TYPE_NUMERIC);
        }
        $sheet->getStyle("D4:{$last}{$line}")->getNumberFormat()->setFormatCode('#,##0.00 "€";[Red]-#,##0.00 "€"');
        $sheet->getStyle("A3:{$last}3")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A3:{$last}3")->getFill()->setFillType('solid')->getStartColor()->setARGB('FF605CA8');
        $sheet->getStyle("A3:{$last}3")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(3)->setRowHeight(42);
        $sheet->getStyle("A{$line}:{$last}{$line}")->getFont()->setBold(true);
        $sheet->freezePane('D4');
        $sheet->setAutoFilter('A3:' . $last . max(3, $line - 1));
        foreach (range(1, count($columns)) as $col) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth($col === 2 || $col === 3 ? 30 : 21);
        $notes = $book->createSheet()->setTitle('Critérios');
        $notes->setCellValue('A1', DriverDepositGlobalReportService::NOTES);
        $notes->getColumnDimension('A')->setWidth(100);
        $notes->getStyle('A1')->getAlignment()->setWrapText(true);
        $notes->getRowDimension(1)->setRowHeight(150);
        $book->setActiveSheetIndex(0);
        return $book;
    }
}
