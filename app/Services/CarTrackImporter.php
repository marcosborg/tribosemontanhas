<?php

namespace App\Services;

use App\Models\CarTrack;
use App\Models\CompanyPark;
use App\Models\CompanyExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class CarTrackImporter
{
    public const COMPANY_PARK_SOURCE_TYPE = 'via_verde';
    public const COMPANY_EXPENSE_SOURCE_TYPE = 'via_verde_annual_fee';
    public const ANNUAL_FEE_REASON = 'annual_fee';

    public function import(string $filePath, string $originalName, int $tvdeWeekId): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsv($filePath),
            'xlsx' => $this->readXlsx($filePath),
            default => throw new RuntimeException('Formato não suportado. Usa CSV, TXT ou XLSX.'),
        };

        if (count($rows) < 2) {
            throw new RuntimeException('O ficheiro não contém linhas para importar.');
        }

        $header = $rows[0];
        $licensePlateColumn = $this->findHeaderIndex($header, ['license plate']);
        $serviceDescriptionColumn = $this->findHeaderIndex($header, ['service description']);
        $marketDescriptionColumn = $this->findHeaderIndex($header, ['market description']);
        $dateColumn = $this->findHeaderIndex($header, ['entry date']);
        $valueColumn = $this->findHeaderIndex($header, ['liquid value', 'value']);
        $invoiceValueColumn = $this->findHeaderIndex($header, ['value']);

        if ($licensePlateColumn === null || $marketDescriptionColumn === null || $dateColumn === null || $valueColumn === null) {
            throw new RuntimeException('O ficheiro Via Verde não tem as colunas obrigatórias: License Plate, Market Description, Entry Date e Value/Liquid Value.');
        }

        $entries = [];
        $annualFees = [];
        $classifier = app(CarTrackClassificationService::class);

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            $serviceDescription = $this->normalizeText($serviceDescriptionColumn !== null ? ($row[$serviceDescriptionColumn] ?? null) : null);
            $marketDescription = $this->normalizeText($row[$marketDescriptionColumn] ?? null);
            $isAnnualFee = $this->isAnnualFee($marketDescription);

            if (! $isAnnualFee && $serviceDescription !== null && $this->shouldSkipDescription($serviceDescription)) {
                continue;
            }

            $licensePlate = $this->normalizeLicensePlate($row[$licensePlateColumn] ?? null);
            $date = $this->normalizeDateTime($row[$dateColumn] ?? null);
            $value = $this->normalizeNumber($row[$valueColumn] ?? null);

            if ($licensePlate === null || $date === null) {
                continue;
            }

            $classification = $isAnnualFee
                ? $classifier->classifyCompanyExpense($licensePlate, self::ANNUAL_FEE_REASON)
                : $classifier->classify($licensePlate, $date);
            $fingerprint = $this->fingerprint($row);

            $entries[] = array_merge([
                'tvde_week_id' => $tvdeWeekId,
                'license_plate' => $licensePlate,
                'date' => $date,
                'value' => $value,
                'source_filename' => $originalName,
                'source_row_number' => $rowNumber,
                'source_fingerprint' => $fingerprint,
                'service_description' => $serviceDescription,
                'market_description' => $marketDescription,
                'created_at' => now(),
                'updated_at' => now(),
            ], $classification);

            if ($isAnnualFee && ($classification['classification_status'] ?? null) === CarTrackClassificationService::STATUS_COMPANY) {
                $annualFees[] = [
                    'company_id' => $classification['company_id'],
                    'license_plate' => $licensePlate,
                    'date' => $date,
                    'value' => $value,
                    'invoice_value' => $this->normalizeNumber($invoiceValueColumn !== null ? ($row[$invoiceValueColumn] ?? null) : null),
                    'description' => $marketDescription,
                    'source_filename' => $originalName,
                    'source_row_number' => $rowNumber,
                    'source_fingerprint' => $fingerprint,
                    'source_payload' => $this->sourcePayload($header, $row),
                ];
            }
        }

        if ($entries === []) {
            throw new RuntimeException('Não foi possível encontrar linhas válidas no ficheiro Via Verde.');
        }

        $inserted = 0;
        $duplicates = 0;
        $companyExpenses = 0;

        DB::transaction(function () use ($entries, $annualFees, $tvdeWeekId, &$inserted, &$duplicates, &$companyExpenses) {
            foreach ($entries as $entry) {
                if ($this->movementExists($entry)) {
                    $duplicates++;
                    continue;
                }

                CarTrack::create($entry);
                $inserted++;
            }

            foreach ($annualFees as $annualFee) {
                if ($this->createAnnualFeeCompanyExpense($annualFee)) {
                    $companyExpenses++;
                }
            }

            $this->rebuildCompanyParkAggregates($tvdeWeekId);
        });

        return [
            'total' => count($entries),
            'driver' => count(array_filter($entries, fn ($entry) => $entry['classification_status'] === CarTrackClassificationService::STATUS_DRIVER)),
            'company' => count(array_filter($entries, fn ($entry) => $entry['classification_status'] === CarTrackClassificationService::STATUS_COMPANY)),
            'manual' => count(array_filter($entries, fn ($entry) => $entry['classification_status'] === CarTrackClassificationService::STATUS_MANUAL)),
            'inserted' => $inserted,
            'duplicates' => $duplicates,
            'company_expenses' => $companyExpenses,
        ];
    }

    private function rebuildCompanyParkAggregates(int $tvdeWeekId): void
    {
        $totals = CarTrack::query()
            ->where('tvde_week_id', $tvdeWeekId)
            ->where('classification_status', CarTrackClassificationService::STATUS_COMPANY)
            ->where(function ($query) {
                $query->whereNull('classification_reason')
                    ->orWhere('classification_reason', '!=', self::ANNUAL_FEE_REASON);
            })
            ->whereNotNull('company_id')
            ->selectRaw('company_id, SUM(value) as total')
            ->groupBy('company_id')
            ->pluck('total', 'company_id');

        CompanyPark::withTrashed()
            ->where('tvde_week_id', $tvdeWeekId)
            ->where('source_type', self::COMPANY_PARK_SOURCE_TYPE)
            ->whereNotIn('company_id', $totals->keys())
            ->forceDelete();

        foreach ($totals as $companyId => $value) {
            CompanyPark::withTrashed()->updateOrCreate(
                [
                    'tvde_week_id' => $tvdeWeekId,
                    'company_id' => $companyId,
                    'source_type' => self::COMPANY_PARK_SOURCE_TYPE,
                ],
                [
                    'value' => round($value, 2),
                    'fleet_management' => false,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function movementExists(array $entry): bool
    {
        if (CarTrack::withTrashed()->where('source_fingerprint', $entry['source_fingerprint'])->exists()) {
            return true;
        }

        return CarTrack::withTrashed()
            ->where('tvde_week_id', $entry['tvde_week_id'])
            ->where('license_plate', $entry['license_plate'])
            ->where('date', $entry['date'])
            ->where('value', number_format((float) $entry['value'], 2, '.', ''))
            ->exists();
    }

    private function createAnnualFeeCompanyExpense(array $annualFee): bool
    {
        if (CompanyExpense::withTrashed()
            ->where('source_type', self::COMPANY_EXPENSE_SOURCE_TYPE)
            ->where('source_fingerprint', $annualFee['source_fingerprint'])
            ->exists()) {
            return false;
        }

        $date = Carbon::parse($annualFee['date'])->format(config('panel.date_format'));
        CompanyExpense::create([
            'company_id' => $annualFee['company_id'],
            'expense_mode' => CompanyExpense::MODE_ACCOUNTING,
            'expense_type' => 'Portagens',
            'date' => $date,
            'description' => $annualFee['description'] . ' — ' . $annualFee['license_plate'],
            'value' => $annualFee['value'],
            'invoice_value' => $annualFee['invoice_value'] ?: $annualFee['value'],
            'vat' => 23,
            'is_paid' => true,
            'paid_at' => Carbon::parse($annualFee['date']),
            'payment_reference' => 'Via Verde: ' . $annualFee['source_filename'] . ' / linha ' . $annualFee['source_row_number'],
            'pay_to' => 'Via Verde',
            'source_type' => self::COMPANY_EXPENSE_SOURCE_TYPE,
            'source_filename' => $annualFee['source_filename'],
            'source_row_number' => $annualFee['source_row_number'],
            'source_fingerprint' => $annualFee['source_fingerprint'],
            'source_payload' => $annualFee['source_payload'],
            'name' => 'Anuidade Via Verde',
            'weekly_value' => $annualFee['value'],
            'start_date' => $date,
            'end_date' => $date,
            'qty' => 1,
        ]);

        return true;
    }

    protected function shouldSkipDescription(string $description): bool
    {
        $normalized = mb_strtolower($description);

        return str_contains($normalized, 'mobilidade') || str_contains($normalized, 'acessórios') || str_contains($normalized, 'acessorios');
    }

    protected function isAnnualFee(?string $marketDescription): bool
    {
        return $marketDescription !== null
            && str_contains(mb_strtolower(trim($marketDescription)), 'anuidade');
    }

    private function fingerprint(array $row): string
    {
        $normalized = array_map(function ($value) {
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return trim((string) $value);
        }, $row);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function sourcePayload(array $header, array $row): array
    {
        $payload = [];

        foreach ($header as $index => $label) {
            $key = trim((string) $label);
            if ($key !== '') {
                $payload[$key] = $row[$index] ?? null;
            }
        }

        return $payload;
    }

    protected function normalizeLicensePlate($value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }

    protected function findHeaderIndex(array $header, array $candidates): ?int
    {
        $normalizedHeader = [];

        foreach ($header as $index => $label) {
            $normalizedHeader[$this->normalizeHeader((string) $label)] = $index;
        }

        foreach ($candidates as $candidate) {
            $key = $this->normalizeHeader($candidate);

            if (array_key_exists($key, $normalizedHeader)) {
                return $normalizedHeader[$key];
            }
        }

        return null;
    }

    protected function normalizeHeader(string $value): string
    {
        return trim(mb_strtolower($value));
    }

    protected function normalizeText($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDateTime($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Encontrada uma data inválida no ficheiro Via Verde.');
        }
    }

    protected function normalizeNumber($value): float
    {
        $value = trim((string) $value);

        if ($value === '' || strtoupper($value) === 'N/A') {
            return 0.0;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $value);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    protected function readCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o ficheiro CSV.');
        }

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rows[0][0]);
        }

        return $rows;
    }

    protected function readXlsx(string $filePath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão PHP zip não está ativa no servidor. É necessária para importar XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Não foi possível abrir o ficheiro XLSX.');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xml = simplexml_load_string($sharedStringsXml);
            foreach ($xml->si as $item) {
                if (isset($item->t)) {
                    $sharedStrings[] = (string) $item->t;
                    continue;
                }

                $text = '';
                foreach ($item->r as $run) {
                    $text .= (string) $run->t;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            throw new RuntimeException('Não foi possível ler a folha principal do ficheiro XLSX.');
        }

        $worksheet = simplexml_load_string($sheetXml);
        $namespaces = $worksheet->getNamespaces(true);
        if (isset($namespaces[''])) {
            $worksheet->registerXPathNamespace('a', $namespaces['']);
        }

        $rows = [];
        foreach ($worksheet->xpath('//a:sheetData/a:row') as $row) {
            $currentRow = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnLettersToIndex(preg_replace('/\d+/', '', $reference));
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? $value;
                } elseif ((string) $cell['t'] === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                }

                $currentRow[$columnIndex] = $value;
            }

            if ($currentRow !== []) {
                ksort($currentRow);
                $rows[] = array_replace(array_fill(0, max(array_keys($currentRow)) + 1, ''), $currentRow);
            }
        }

        $zip->close();

        return $rows;
    }

    protected function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
