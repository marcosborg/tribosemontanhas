<?php

namespace App\Services;

use App\Models\CarTrack;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class CarTrackImporter
{
    public function import(string $filePath, string $originalName, int $tvdeWeekId): int
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

        $entries = [];

        foreach (array_slice($rows, 1) as $row) {
            $description = $this->normalizeText($row[4] ?? null);

            if ($description !== null && $this->shouldSkipDescription($description)) {
                continue;
            }

            $licensePlate = $this->normalizeLicensePlate($row[0] ?? null);
            $date = $this->normalizeDateTime($row[7] ?? null);
            $value = $this->normalizeNumber($row[18] ?? null);

            if ($licensePlate === null || $date === null) {
                continue;
            }

            $entries[] = [
                'tvde_week_id' => $tvdeWeekId,
                'license_plate' => $licensePlate,
                'date' => $date,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($entries === []) {
            throw new RuntimeException('Não foi possível encontrar linhas válidas no ficheiro Via Verde.');
        }

        DB::transaction(function () use ($entries, $tvdeWeekId) {
            CarTrack::query()->where('tvde_week_id', $tvdeWeekId)->delete();
            CarTrack::query()->insert($entries);
        });

        return count($entries);
    }

    protected function shouldSkipDescription(string $description): bool
    {
        $normalized = mb_strtolower($description);

        return str_contains($normalized, 'mobilidade') || str_contains($normalized, 'acessórios') || str_contains($normalized, 'acessorios');
    }

    protected function normalizeLicensePlate($value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
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
                }

                $currentRow[$columnIndex] = $value;
            }

            if ($currentRow !== []) {
                ksort($currentRow);
                $rows[] = array_values($currentRow);
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
