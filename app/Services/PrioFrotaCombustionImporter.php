<?php

namespace App\Services;

use App\Models\CombustionTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class PrioFrotaCombustionImporter
{
    private const COLUMN_DATE = 3; // D
    private const COLUMN_TIME = 4; // E
    private const COLUMN_CARD = 5; // F
    private const COLUMN_TOTAL = 18; // S

    public function import(string $path, int $tvdeWeekId, ?string $originalName = null): int
    {
        $rows = $this->parseRows($path, $tvdeWeekId, $originalName);
        $cards = collect($rows)->pluck('card')->filter()->unique()->values()->all();

        DB::transaction(function () use ($tvdeWeekId, $rows, $cards): void {
            if ($cards !== []) {
                CombustionTransaction::where('tvde_week_id', $tvdeWeekId)
                    ->whereIn('card', $cards)
                    ->delete();
            }

            if ($rows !== []) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    CombustionTransaction::insert($chunk);
                }
            }
        });

        return count($rows);
    }

    public function parseRows(string $path, int $tvdeWeekId, ?string $originalName = null): array
    {
        $extension = strtolower((string) pathinfo($originalName ?: $path, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            $rows = $this->readCsvRows($path);
        } elseif ($extension === 'xlsx') {
            $rows = $this->readXlsxRows($path);
        } elseif ($extension === 'xls') {
            throw new RuntimeException('Ficheiros XLS não são suportados neste servidor Linux. Exporta para XLSX ou CSV.');
        } else {
            throw new RuntimeException('Formato não suportado. Usa CSV ou XLSX.');
        }

        $dataRows = array_slice($rows, 4);
        $insert = [];

        foreach ($dataRows as $row) {
            $card = trim((string) ($row[self::COLUMN_CARD] ?? ''));

            if ($card === '') {
                continue;
            }

            $insert[] = [
                'tvde_week_id' => $tvdeWeekId,
                'card' => $card,
                'amount' => 0,
                'total' => $this->toDecimal($row[self::COLUMN_TOTAL] ?? null),
                'transaction_date' => $this->toMysqlDateTime(
                    $row[self::COLUMN_DATE] ?? null,
                    $row[self::COLUMN_TIME] ?? null
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $insert;
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o ficheiro CSV.');
        }

        try {
            $delimiter = $this->detectDelimiter($path);
            $rows = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function readXlsxRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão PHP zip não está ativa no servidor. É necessária para importar XLSX.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir o ficheiro XLSX.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($sheetXml === false) {
                throw new RuntimeException('A primeira folha do XLSX não foi encontrada.');
            }

            $sheet = simplexml_load_string($sheetXml);

            if ($sheet === false) {
                throw new RuntimeException('Não foi possível ler o XML da folha do XLSX.');
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
                    }

                    $current[$columnIndex] = $value;
                }

                if ($current === []) {
                    continue;
                }

                ksort($current);
                $maxIndex = max(array_keys($current));
                $normalized = array_fill(0, $maxIndex + 1, '');

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

    private function columnReferenceToIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
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

    private function toDecimal($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        $normalized = str_replace(["\xc2\xa0", ' ', '€'], '', $value);
        $normalized = preg_replace('/[^0-9,\.\-]/', '', $normalized) ?? '';

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function toMysqlDateTime($dateValue, $timeValue): ?string
    {
        $dateValue = trim((string) ($dateValue ?? ''));
        $timeValue = trim((string) ($timeValue ?? ''));

        if ($dateValue === '') {
            return null;
        }

        if (is_numeric($dateValue)) {
            $date = Carbon::createFromDate(1899, 12, 30)->addDays((int) floor((float) $dateValue));
            $secondsInDay = ((float) $dateValue - floor((float) $dateValue)) * 86400;
            $date->addSeconds((int) round($secondsInDay));

            if ($timeValue !== '') {
                $date = $this->applyTimeToDate($date, $timeValue);
            }

            return $date->format('Y-m-d H:i:s');
        }

        $dateFormats = ['d/m/Y', 'd/m/y', 'd-m-Y', 'd-m-y', 'Y-m-d'];

        foreach ($dateFormats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateValue);

                if ($date->year < 100) {
                    $date->year($date->year + 2000);
                }

                if ($timeValue !== '') {
                    $date = $this->applyTimeToDate($date, $timeValue);
                }

                return $date->format('Y-m-d H:i:s');
            } catch (\Throwable $exception) {
                continue;
            }
        }

        try {
            $date = Carbon::parse(trim($dateValue . ' ' . $timeValue));
            return $date->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            throw new RuntimeException("Data/hora inválida no ficheiro: [{$dateValue} {$timeValue}]");
        }
    }

    private function applyTimeToDate(Carbon $date, string $timeValue): Carbon
    {
        $timeFormats = ['H:i:s', 'H:i'];

        foreach ($timeFormats as $format) {
            try {
                $time = Carbon::createFromFormat($format, $timeValue);
                return $date->copy()->setTime($time->hour, $time->minute, $time->second);
            } catch (\Throwable $exception) {
                continue;
            }
        }

        if (is_numeric($timeValue)) {
            $secondsInDay = ((float) $timeValue) * 86400;
            return $date->copy()->startOfDay()->addSeconds((int) round($secondsInDay));
        }

        throw new RuntimeException("Hora inválida no ficheiro: [{$timeValue}]");
    }
}
