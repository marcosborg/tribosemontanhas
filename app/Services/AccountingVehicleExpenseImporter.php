<?php

namespace App\Services;

use App\Models\VehicleExpense;
use App\Models\VehicleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class AccountingVehicleExpenseImporter
{
    private const REQUIRED_HEADERS = [
        'date' => ['data', 'column2'],
        'description' => ['descricao banco', 'descrição banco', 'descricao', 'descrição'],
        'value' => ['valor'],
        'expense_type' => ['nt', 'tipo', 'tipo despesa', 'tipo de despesa'],
        'license_plate' => ['matricula', 'matrícula'],
    ];

    public function import(string $path, string $originalName): array
    {
        $rows = $this->readRows($path, $originalName);

        if (count($rows) < 2) {
            throw new RuntimeException('O ficheiro nao contem linhas para importar.');
        }

        $header = array_shift($rows);
        $columns = $this->resolveColumns($header);
        $expenseTypes = $this->expenseTypes();
        $imported = 0;
        $failed = [];

        DB::transaction(function () use ($rows, $columns, $expenseTypes, &$imported, &$failed): void {
            foreach ($rows as $index => $row) {
                $line = $index + 2;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $licensePlate = $this->normalizeText($row[$columns['license_plate']] ?? null);
                $expenseType = $this->normalizeText($row[$columns['expense_type']] ?? null);
                $rawValue = $row[$columns['value']] ?? null;
                $rawDate = $row[$columns['date']] ?? null;

                $vehicleId = $this->resolveVehicleItemId($licensePlate);
                $date = $this->normalizeDate($rawDate);
                $value = $this->normalizeAmount($rawValue);
                $resolvedExpenseType = $expenseTypes[$expenseType] ?? null;

                $errors = [];

                if ($licensePlate === null || $vehicleId === null) {
                    $errors[] = 'Matricula inexistente';
                }

                if ($expenseType === null || $resolvedExpenseType === null) {
                    $errors[] = 'Tipo de despesa inexistente';
                }

                if ($date === null) {
                    $errors[] = 'Data invalida';
                }

                if ($value === null || $value <= 0) {
                    $errors[] = 'Valor invalido';
                }

                if ($errors === [] && $this->expenseAlreadyExists($vehicleId, $resolvedExpenseType, $date, $value)) {
                    $errors[] = 'Despesa ja existente';
                }

                if ($errors !== []) {
                    $failed[] = [
                        'line' => $line,
                        'license_plate' => $licensePlate,
                        'expense_type' => $expenseType,
                        'value' => $rawValue,
                        'reason' => implode('; ', $errors),
                    ];
                    continue;
                }

                $description = $this->buildDescription($row, $columns);

                VehicleExpense::create([
                    'vehicle_item_id' => $vehicleId,
                    'expense_type' => $resolvedExpenseType,
                    'date' => $date,
                    'description' => $description,
                    'value' => $value,
                    'invoice_value' => $value,
                    'vat' => 0,
                    'is_paid' => true,
                    'paid_at' => Carbon::createFromFormat(config('panel.date_format'), $date)->startOfDay(),
                    'payment_reference' => $this->normalizeText($row[$columns['description']] ?? null),
                ]);

                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'failed' => $failed,
        ];
    }

    private function readRows(string $path, string $originalName): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => $this->readCsvRows($path),
            'xlsx' => $this->readXlsxRows($path),
            'xls' => $this->readSpreadsheetRows($path),
            default => throw new RuntimeException('Formato nao suportado. Usa CSV, TXT, XLS ou XLSX.'),
        };
    }

    private function readSpreadsheetRows(string $path): array
    {
        $reader = new \SpreadsheetReader($path);
        $rows = [];

        foreach ($reader as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel abrir o ficheiro.');
        }

        try {
            $delimiter = $this->detectDelimiter($path);
            $rows = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }

            if (isset($rows[0][0])) {
                $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rows[0][0]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function readXlsxRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensao PHP zip nao esta ativa no servidor. E necessaria para importar XLSX.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Nao foi possivel abrir o ficheiro XLSX.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($sheetXml === false) {
                throw new RuntimeException('A primeira folha do XLSX nao foi encontrada.');
            }

            $sheet = simplexml_load_string($sheetXml);

            if ($sheet === false) {
                throw new RuntimeException('Nao foi possivel ler a primeira folha do XLSX.');
            }

            $rows = [];

            foreach ($sheet->sheetData->row as $row) {
                $current = [];

                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    $columnIndex = $this->columnReferenceToIndex($reference);
                    $type = (string) $cell['t'];
                    $value = isset($cell->v) ? (string) $cell->v : '';

                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                        $value = (string) $cell->is->t;
                    }

                    $current[$columnIndex] = $value;
                }

                if ($current === []) {
                    continue;
                }

                ksort($current);
                $normalized = array_fill(0, max(array_keys($current)) + 1, null);

                foreach ($current as $index => $value) {
                    $normalized[$index] = $value;
                }

                $rows[] = $normalized;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStringsXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sharedStringsXml);

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function resolveColumns(array $header): array
    {
        $normalizedHeader = [];

        foreach ($header as $index => $label) {
            $key = $this->normalizeHeader((string) $label);

            if ($key !== '') {
                $normalizedHeader[$key] = $index;
            }
        }

        $columns = [];

        foreach (self::REQUIRED_HEADERS as $field => $candidates) {
            foreach ($candidates as $candidate) {
                $key = $this->normalizeHeader($candidate);

                if (array_key_exists($key, $normalizedHeader)) {
                    $columns[$field] = $normalizedHeader[$key];
                    break;
                }
            }

            if (!array_key_exists($field, $columns)) {
                throw new RuntimeException('Coluna obrigatoria em falta: ' . $field);
            }
        }

        $columns['detail'] = $normalizedHeader[$this->normalizeHeader('Detalhe')] ?? null;

        return $columns;
    }

    private function expenseTypes(): array
    {
        $values = collect(VehicleExpense::EXPENSE_TYPE_RADIO)
            ->flatMap(fn ($label, $key) => [$key, $label])
            ->merge(VehicleExpense::query()->select('expense_type')->distinct()->pluck('expense_type'))
            ->filter()
            ->unique()
            ->values();

        return $values->mapWithKeys(fn ($value) => [trim((string) $value) => trim((string) $value)])->all();
    }

    private function resolveVehicleItemId(?string $licensePlate): ?int
    {
        if ($licensePlate === null) {
            return null;
        }

        $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($licensePlate));

        if ($normalized === '') {
            return null;
        }

        return VehicleItem::whereRaw("REPLACE(REPLACE(UPPER(license_plate), ' ', ''), '-', '') = ?", [$normalized])
            ->value('id');
    }

    private function buildDescription(array $row, array $columns): ?string
    {
        $parts = [
            $this->normalizeText($row[$columns['description']] ?? null),
        ];

        if ($columns['detail'] !== null) {
            $parts[] = $this->normalizeText($row[$columns['detail']] ?? null);
        }

        $description = implode("\n", array_filter($parts));

        return $description !== '' ? $description : null;
    }

    private function expenseAlreadyExists(?int $vehicleId, ?string $expenseType, ?string $date, ?float $value): bool
    {
        if ($vehicleId === null || $expenseType === null || $date === null || $value === null) {
            return false;
        }

        return VehicleExpense::query()
            ->where('vehicle_item_id', $vehicleId)
            ->where('expense_type', $expenseType)
            ->whereDate('date', $date)
            ->where('value', number_format($value, 2, '.', ''))
            ->exists();
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromDate(1899, 12, 30)
                ->addDays((int) floor((float) $value))
                ->format(config('panel.date_format'));
        }

        $value = trim((string) $value);
        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date->year < 100) {
                    $date->year($date->year + 2000);
                }

                return $date->format(config('panel.date_format'));
            } catch (\Throwable $exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format(config('panel.date_format'));
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function normalizeAmount($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return abs((float) $value);
        }

        $value = preg_replace('/[^0-9,\.\-]/', '', str_replace(["\xc2\xa0", ' '], '', (string) $value)) ?? '';

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? abs((float) $value) : null;
    }

    private function normalizeText($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = strtr($value, [
            'á' => 'a',
            'à' => 'a',
            'ã' => 'a',
            'â' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'õ' => 'o',
            'ô' => 'o',
            'ú' => 'u',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeText($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function detectDelimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 4096);
        $candidates = [';', ',', "\t"];
        $bestDelimiter = ';';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($sample, $candidate);

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function columnReferenceToIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }
}
