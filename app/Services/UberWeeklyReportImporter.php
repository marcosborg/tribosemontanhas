<?php

namespace App\Services;

use App\Models\TvdeActivity;
use Illuminate\Support\Facades\DB;
use SpreadsheetReader;

class UberWeeklyReportImporter
{
    private const COMPANY_ID = 27;
    private const OPERATOR_ID = 1;
    private const EXCLUDED_DRIVER_CODES = [
        '4960b6d9-9b4a-4d6c-9b2c-4010c6298c32',
    ];
    private const COLUMN_DRIVER_CODE = 0; // A
    private const COLUMN_NET = 3; // D
    private const COLUMN_GROSS = 6; // G

    public function import(string $path, int $tvdeWeekId): int
    {
        $rows = $this->parseRows($path, $tvdeWeekId);

        DB::transaction(function () use ($tvdeWeekId, $rows): void {
            TvdeActivity::where([
                'tvde_week_id' => $tvdeWeekId,
                'tvde_operator_id' => self::OPERATOR_ID,
                'company_id' => self::COMPANY_ID,
            ])->delete();

            if ($rows !== []) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    TvdeActivity::insert($chunk);
                }
            }
        });

        return count($rows);
    }

    public function parseRows(string $path, int $tvdeWeekId): array
    {
        $reader = new SpreadsheetReader($path);
        $rows = [];

        foreach ($reader as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $driverCode = trim((string) ($row[self::COLUMN_DRIVER_CODE] ?? ''));
            $net = $this->toDecimal($row[self::COLUMN_NET] ?? null);
            $gross = $this->toDecimal($row[self::COLUMN_GROSS] ?? null);

            if ($driverCode === '' || in_array($driverCode, self::EXCLUDED_DRIVER_CODES, true)) {
                continue;
            }

            $rows[] = [
                'tvde_week_id' => $tvdeWeekId,
                'tvde_operator_id' => self::OPERATOR_ID,
                'company_id' => self::COMPANY_ID,
                'driver_code' => $driverCode,
                'gross' => $gross,
                'net' => $net,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }

    private function toDecimal($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $value);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
