<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeslaCharging extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'tesla_chargings';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'value',
        'license',
        'datetime',
        'tvde_week_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = ['datetime' => 'datetime'];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function tvde_week()
    {
        return $this->belongsTo(TvdeWeek::class, 'tvde_week_id');
    }

    public function resolveUsageValidation(): array
    {
        $identifier = $this->normalizeIdentifier($this->license);
        $chargingAt = $this->datetime ? Carbon::parse($this->datetime) : null;

        if ($identifier === '' || $chargingAt === null) {
            return [
                'validation_status' => 'does_not_exist',
                'resolved_driver_id' => null,
                'resolved_driver_name' => null,
                'resolved_vehicle_license_plate' => null,
                'validation_issue' => 'Registo sem identificador ou data/hora valida',
            ];
        }

        $matches = VehicleUsage::query()
            ->with(['driver', 'vehicle_item'])
            ->where('start_date', '<=', $chargingAt)
            ->where(function ($query) use ($chargingAt) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $chargingAt);
            })
            ->whereHas('vehicle_item', function ($query) use ($identifier) {
                $query->where(function ($builder) use ($identifier) {
                    $builder
                        ->whereRaw("REPLACE(REPLACE(UPPER(COALESCE(vehicle_items.license_plate, '')), ' ', ''), '-', '') = ?", [$identifier])
                        ->orWhereRaw("REPLACE(REPLACE(UPPER(COALESCE(vehicle_items.vin, '')), ' ', ''), '-', '') = ?", [$identifier]);
                });
            })
            ->get();

        if ($matches->isEmpty()) {
            return [
                'validation_status' => 'does_not_exist',
                'resolved_driver_id' => null,
                'resolved_driver_name' => null,
                'resolved_vehicle_license_plate' => null,
                'validation_issue' => 'Sem utilizacao nesse momento',
            ];
        }

        $validMatches = $matches->filter(fn (VehicleUsage $usage) => !empty($usage->driver_id));
        $distinctDriverIds = $validMatches->pluck('driver_id')->filter()->unique()->values();
        $resolvedPlate = $matches->pluck('vehicle_item.license_plate')->filter()->unique()->values();

        if ($distinctDriverIds->count() === 1) {
            $driver = $validMatches->firstWhere('driver_id', $distinctDriverIds->first())?->driver;

            return [
                'validation_status' => 'exists',
                'resolved_driver_id' => $driver?->id,
                'resolved_driver_name' => $driver?->name,
                'resolved_vehicle_license_plate' => $resolvedPlate->count() === 1 ? $resolvedPlate->first() : null,
                'validation_issue' => null,
            ];
        }

        return [
            'validation_status' => 'does_not_exist',
            'resolved_driver_id' => null,
            'resolved_driver_name' => null,
            'resolved_vehicle_license_plate' => $resolvedPlate->count() === 1 ? $resolvedPlate->first() : null,
            'validation_issue' => $distinctDriverIds->isEmpty()
                ? 'Viatura sem condutor atribuido nesse momento'
                : 'Conflito de utilizacoes nesse momento',
        ];
    }

    private function normalizeIdentifier(?string $value): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim((string) $value)));
    }
}
