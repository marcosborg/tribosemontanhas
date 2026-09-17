<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleMaintenanceSchedule extends Model
{
    use HasFactory;

    public const TYPE_REVIEW = 'review';
    public const TYPE_TIRE_ROTATION = 'tire_rotation';
    public const TYPE_GEARBOX = 'gearbox';
    public const TYPE_TIMING_KIT = 'timing_kit';

    public const TYPE_SELECT = [
        self::TYPE_REVIEW => 'Revisão',
        self::TYPE_TIRE_ROTATION => 'Rodar pneus',
        self::TYPE_GEARBOX => 'Revisão da caixa',
        self::TYPE_TIMING_KIT => 'Kit correia/corrente',
    ];

    public const TIRE_ROTATION_INTERVAL_KM = 15000;

    protected $fillable = [
        'vehicle_item_id',
        'type',
        'enabled',
        'interval_km',
        'last_service_km',
        'last_service_date',
        'notes',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'interval_km' => 'integer',
        'last_service_km' => 'integer',
        'last_service_date' => 'date',
    ];

    public function vehicle_item()
    {
        return $this->belongsTo(VehicleItem::class, 'vehicle_item_id');
    }

    public function records()
    {
        return $this->hasMany(VehicleMaintenanceRecord::class, 'vehicle_maintenance_schedule_id');
    }

    public function nextServiceKm(): ?int
    {
        if ($this->last_service_km === null || ! $this->interval_km) {
            return null;
        }

        return $this->last_service_km + $this->interval_km;
    }

    public function statusFor(?float $currentKm): string
    {
        $nextKm = $this->nextServiceKm();

        if ($currentKm === null || $nextKm === null) {
            return 'unknown';
        }

        $remaining = $nextKm - $currentKm;
        if ($remaining <= 0) {
            return 'due';
        }

        $warningKm = max(1000, (int) round($this->interval_km * 0.1));

        return $remaining <= $warningKm ? 'soon' : 'ok';
    }
}
