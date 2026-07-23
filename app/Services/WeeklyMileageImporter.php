<?php

namespace App\Services;

use App\Models\TvdeWeek;
use App\Models\VehicleItem;
use App\Models\VehicleUsage;
use App\Models\WeeklyVehicleExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class WeeklyMileageImporter
{
    public function importTesla(string $path, string $originalName, int $weekId): array
    {
        $week = TvdeWeek::findOrFail($weekId);
        $rows = $this->readTeslaRows($path);

        return $this->persist($rows, $week, WeeklyVehicleExpense::SOURCE_TESLA, $originalName);
    }

    public function importCarTrack(string $path, string $originalName, int $weekId): array
    {
        $week = TvdeWeek::findOrFail($weekId);
        $rows = $this->readCarTrackRows($path, $week);

        return $this->persist($rows, $week, WeeklyVehicleExpense::SOURCE_CARTRACK, $originalName);
    }

    public function readTeslaRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o ficheiro Tesla.');
        }

        try {
            $firstLine = (string) fgets($handle);
            rewind($handle);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $header = fgetcsv($handle, 0, $delimiter);
            if (!is_array($header)) {
                throw new RuntimeException('O ficheiro Tesla não contém cabeçalho.');
            }

            $columns = $this->headerColumns($header);
            $plateColumn = $this->requiredColumn($columns, ['matricula'], 'Matrícula');
            $kmColumn = $this->requiredColumn($columns, ['quilometros'], 'Quilómetros');
            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                $plate = trim((string) ($values[$plateColumn] ?? ''));
                if ($plate === '' || str_contains(Str::lower($plate), 'confidencial')) {
                    continue;
                }

                $odometer = $this->number($values[$kmColumn] ?? null);
                $rows[] = [
                    'line' => $line,
                    'license_plate' => $plate,
                    'odometer_start' => null,
                    'odometer_end' => $odometer,
                    'distance_km' => null,
                ];
            }

            if ($rows === []) {
                throw new RuntimeException('O ficheiro Tesla não contém linhas válidas.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    public function readCarTrackRows(string $path, TvdeWeek $week): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Não foi possível ler o ficheiro CarTrack: ' . $exception->getMessage(), 0, $exception);
        }

        $this->validateCarTrackPeriod($sheet, $week);
        $headerIndex = null;
        $columns = [];
        foreach ($sheet as $index => $row) {
            $candidate = $this->headerColumns($row);
            if (isset($candidate['matricula'], $candidate['inicio'], $candidate['fim'], $candidate['distancia'])) {
                $headerIndex = $index;
                $columns = $candidate;
                break;
            }
        }
        if ($headerIndex === null) {
            throw new RuntimeException('O cabeçalho Matrícula/Início/Fim/Distância não foi encontrado no CarTrack.');
        }

        $rows = [];
        foreach (array_slice($sheet, $headerIndex + 1, null, true) as $index => $row) {
            $plate = trim((string) ($row[$columns['matricula']] ?? ''));
            if ($plate === '' || Str::startsWith(Str::lower($plate), 'total')) {
                continue;
            }
            $rows[] = [
                'line' => $index + 1,
                'license_plate' => $plate,
                'odometer_start' => $this->number($row[$columns['inicio']] ?? null),
                'odometer_end' => $this->number($row[$columns['fim']] ?? null),
                'distance_km' => $this->number($row[$columns['distancia']] ?? null),
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('O ficheiro CarTrack não contém linhas válidas.');
        }

        return $rows;
    }

    private function persist(array $rows, TvdeWeek $week, string $source, string $originalName): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'pending' => 0, 'failed' => []];
        $vehicles = VehicleItem::query()->get()->keyBy(fn ($vehicle) => $this->plate($vehicle->license_plate));

        DB::transaction(function () use ($rows, $week, $source, $originalName, $vehicles, &$summary): void {
            foreach ($rows as $row) {
                $plateKey = $this->plate($row['license_plate']);
                $vehicle = $vehicles->get($plateKey);
                if (!$vehicle) {
                    $summary['failed'][] = $this->failure($row, 'Matrícula desconhecida');
                    continue;
                }
                if ($row['odometer_end'] === null || $row['odometer_end'] < 0) {
                    $summary['failed'][] = $this->failure($row, 'Leitura final inválida');
                    continue;
                }

                $existing = WeeklyVehicleExpense::query()
                    ->where('vehicle_item_id', $vehicle->id)
                    ->where('tvde_week_id', $week->id)
                    ->first();
                if ($existing && $existing->source === WeeklyVehicleExpense::SOURCE_TESLA && $source === WeeklyVehicleExpense::SOURCE_CARTRACK) {
                    $summary['skipped']++;
                    continue;
                }

                $start = $row['odometer_start'];
                $distance = $row['distance_km'];
                $status = WeeklyVehicleExpense::STATUS_REVIEW;
                $reason = null;

                if ($source === WeeklyVehicleExpense::SOURCE_TESLA) {
                    $previous = $this->previousReading($vehicle->id, $week);
                    if ($previous) {
                        $start = (float) $previous->odometer_end;
                        $distance = round((float) $row['odometer_end'] - $start, 2);
                        if ($distance < 0) {
                            $summary['failed'][] = $this->failure($row, 'A leitura Tesla é inferior à leitura anterior');
                            continue;
                        }
                    } else {
                        $status = WeeklyVehicleExpense::STATUS_BASELINE;
                        $reason = 'Sem histórico anterior';
                    }
                } elseif ($distance === null || $distance < 0 || $start === null) {
                    $summary['failed'][] = $this->failure($row, 'Distância ou leitura inicial inválida');
                    continue;
                }

                $expense = $existing ?: new WeeklyVehicleExpense();
                $expense->fill([
                    'vehicle_item_id' => $vehicle->id,
                    'tvde_week_id' => $week->id,
                    'source' => $source,
                    'status' => $status,
                    'status_reason' => $reason,
                    'odometer_start' => $start,
                    'odometer_end' => $row['odometer_end'],
                    'distance_km' => $distance,
                    'original_filename' => $originalName,
                    'imported_at' => now(),
                    'total_km' => round((float) $row['odometer_end']),
                    'weekly_km' => $distance === null ? null : round($distance),
                ]);
                $expense->save();

                if ($distance !== null) {
                    $this->syncAllocations($expense, $week);
                }
                $expense->refresh();
                $summary[$existing ? 'updated' : 'created']++;
                if ($expense->status !== WeeklyVehicleExpense::STATUS_READY) {
                    $summary['pending']++;
                }
            }
        });

        return $summary;
    }

    private function syncAllocations(WeeklyVehicleExpense $expense, TvdeWeek $week): void
    {
        $manual = $expense->allocations()->where('is_manual', true)->with('driver')->get();
        if ($manual->isNotEmpty()) {
            foreach ($manual as $allocation) {
                $allowance = $allocation->driver->weekly_km_allowance;
                $allocation->update([
                    'allowance_km' => $allowance,
                    'extra_km' => $allowance === null ? null : max(0, (float) $allocation->allocated_km - (float) $allowance),
                ]);
            }
            $this->refreshStatus($expense);
            return;
        }

        $expense->allocations()->delete();
        $driverIds = VehicleUsage::query()
            ->where('vehicle_item_id', $expense->vehicle_item_id)
            ->whereNotNull('driver_id')
            ->where(function ($query) {
                $query->whereNull('usage_exceptions')->orWhere('usage_exceptions', '')->orWhere('usage_exceptions', 'usage');
            })
            ->activeBetween($this->weekDate($week, 'start_date'), $this->weekDate($week, 'end_date'))
            ->distinct()
            ->pluck('driver_id');

        if ($driverIds->count() !== 1) {
            $expense->update(['driver_id' => null, 'status' => WeeklyVehicleExpense::STATUS_REVIEW, 'status_reason' => $driverIds->isEmpty() ? 'Sem motorista na semana' : 'Vários motoristas na semana']);
            return;
        }

        $driver = \App\Models\Driver::find($driverIds->first());
        $allowance = $driver?->weekly_km_allowance;
        $expense->allocations()->create([
            'driver_id' => $driver->id,
            'allocated_km' => $expense->distance_km,
            'allowance_km' => $allowance,
            'extra_km' => $allowance === null ? null : max(0, (float) $expense->distance_km - (float) $allowance),
            'is_manual' => false,
        ]);
        $expense->update([
            'driver_id' => $driver->id,
            'status' => $allowance === null ? WeeklyVehicleExpense::STATUS_REVIEW : WeeklyVehicleExpense::STATUS_READY,
            'status_reason' => $allowance === null ? 'Limite semanal do motorista não configurado' : null,
            'extra_km' => $allowance === null ? null : round(max(0, (float) $expense->distance_km - (float) $allowance)),
        ]);
    }

    public function refreshStatus(WeeklyVehicleExpense $expense): void
    {
        $allocations = $expense->allocations()->get();
        $allocated = (float) $allocations->sum('allocated_km');
        $distance = (float) $expense->distance_km;
        $complete = abs($allocated - $distance) < 0.01 && $allocations->isNotEmpty() && $allocations->every(fn ($allocation) => $allocation->allowance_km !== null);
        $expense->update([
            'driver_id' => $allocations->count() === 1 ? $allocations->first()->driver_id : null,
            'status' => $complete ? WeeklyVehicleExpense::STATUS_READY : WeeklyVehicleExpense::STATUS_REVIEW,
            'status_reason' => $complete ? null : 'Alocação de motoristas incompleta',
            'extra_km' => $complete ? round((float) $allocations->sum('extra_km')) : null,
        ]);
    }

    private function previousReading(int $vehicleId, TvdeWeek $week): ?WeeklyVehicleExpense
    {
        return WeeklyVehicleExpense::query()
            ->join('tvde_weeks', 'tvde_weeks.id', '=', 'weekly_vehicle_expenses.tvde_week_id')
            ->where('weekly_vehicle_expenses.vehicle_item_id', $vehicleId)
            ->whereNotNull('weekly_vehicle_expenses.odometer_end')
            ->where('tvde_weeks.start_date', '<', $this->weekDate($week, 'start_date'))
            ->orderByDesc('tvde_weeks.start_date')
            ->select('weekly_vehicle_expenses.*')
            ->first();
    }

    private function validateCarTrackPeriod(array $rows, TvdeWeek $week): void
    {
        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (!is_string($value) || !str_contains($value, 'Data início:')) {
                    continue;
                }
                if (!preg_match('/Data início:\s*(\d{4}-\d{2}-\d{2}).*Data fim:\s*(\d{4}-\d{2}-\d{2})/u', $value, $matches)) {
                    break;
                }
                $expectedStart = $this->weekDate($week, 'start_date');
                $expectedEnd = $this->weekDate($week, 'end_date');
                if ($matches[1] !== $expectedStart || $matches[2] !== $expectedEnd) {
                    throw new RuntimeException("O período CarTrack {$matches[1]} a {$matches[2]} não corresponde à semana {$expectedStart} a {$expectedEnd}.");
                }
                return;
            }
        }
        throw new RuntimeException('O período do relatório CarTrack não foi encontrado.');
    }

    private function headerColumns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $label) {
            $key = $this->key($label);
            if ($key !== '') {
                $columns[$key] = $index;
            }
        }
        return $columns;
    }

    private function requiredColumn(array $columns, array $candidates, string $label): int
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $columns)) {
                return $columns[$candidate];
            }
        }
        throw new RuntimeException("Coluna obrigatória em falta: {$label}.");
    }

    private function number($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = preg_replace('/[^0-9,.]/u', '', str_replace(["\u{00A0}", ' '], '', (string) $value));
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = strrpos($clean, ',') > strrpos($clean, '.')
                ? str_replace(',', '.', str_replace('.', '', $clean))
                : str_replace(',', '', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function key($value): string
    {
        return Str::slug(preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value)), '_');
    }

    private function weekDate(TvdeWeek $week, string $field): string
    {
        return (string) ($week->getRawOriginal($field) ?: ($week->getAttributes()[$field] ?? ''));
    }

    private function plate($value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper((string) $value)) ?? '';
    }

    private function failure(array $row, string $reason): array
    {
        return ['line' => $row['line'], 'license_plate' => $row['license_plate'], 'reason' => $reason];
    }
}
