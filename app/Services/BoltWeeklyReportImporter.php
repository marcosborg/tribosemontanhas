<?php

namespace App\Services;

use App\Models\TvdeActivity;
use Illuminate\Support\Facades\DB;
use SpreadsheetReader;

class BoltWeeklyReportImporter
{
    private const COMPANY_ID = 27;
    private const OPERATOR_ID = 2;
    private const COLUMN_GROSS = 4; // E
    private const COLUMN_NET = 21; // V
    private const COLUMN_DRIVER_CODE = 27; // AB

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

            if ($driverCode === '') {
                continue;
            }

            $rows[] = [
                'tvde_week_id' => $tvdeWeekId,
                'tvde_operator_id' => self::OPERATOR_ID,
                'company_id' => self::COMPANY_ID,
                'driver_code' => $driverCode,
                'gross' => $this->toDecimal($row[self::COLUMN_GROSS] ?? null),
                'net' => $this->toDecimal($row[self::COLUMN_NET] ?? null),
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
