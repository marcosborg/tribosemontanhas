<?php

namespace App\Services;

use App\Models\TeslaCharging;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TeslaChargingImporter
{
    public function import(string $filePath, string $originalName, int $tvdeWeekId): int
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'txt'], true)) {
            throw new RuntimeException('Formato não suportado. Usa CSV ou TXT.');
        }

        $rows = $this->readCsv($filePath);

        if (count($rows) < 2) {
            throw new RuntimeException('O ficheiro não contém linhas para importar.');
        }

        $entries = [];

        foreach (array_slice($rows, 1) as $row) {
            $datetime = $this->normalizeDateTime($row[1] ?? null);
            $vin = $this->normalizeText($row[5] ?? null);
            $value = $this->normalizeNumber($row[22] ?? null);

            if ($datetime === null || $vin === null) {
                continue;
            }

            $entries[] = [
                'value' => $value,
                'license' => $vin,
                'datetime' => $datetime,
                'tvde_week_id' => $tvdeWeekId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($entries === []) {
            throw new RuntimeException('Não foi possível encontrar linhas válidas no ficheiro Tesla Charging.');
        }

        DB::transaction(function () use ($entries, $tvdeWeekId) {
            TeslaCharging::query()->where('tvde_week_id', $tvdeWeekId)->delete();
            TeslaCharging::query()->insert($entries);
        });

        return count($entries);
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

    protected function normalizeDateTime($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Encontrada uma data inválida no ficheiro Tesla Charging.');
        }
    }

    protected function normalizeText($value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
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
}
