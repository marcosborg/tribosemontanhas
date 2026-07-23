<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyVehicleExpense extends Model
{
    use SoftDeletes, HasFactory;

    public const SOURCE_TESLA = 'tesla';
    public const SOURCE_CARTRACK = 'cartrack';
    public const STATUS_READY = 'ready';
    public const STATUS_REVIEW = 'review';
    public const STATUS_BASELINE = 'baseline';

    public $table = 'weekly_vehicle_expenses';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'vehicle_item_id',
        'driver_id',
        'tvde_week_id',
        'source',
        'status',
        'status_reason',
        'odometer_start',
        'odometer_end',
        'distance_km',
        'original_filename',
        'imported_at',
        'total_km',
        'weekly_km',
        'extra_km',
        'transfers',
        'deposit',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'odometer_start' => 'decimal:2',
        'odometer_end' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'imported_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function vehicle_item()
    {
        return $this->belongsTo(VehicleItem::class, 'vehicle_item_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function tvde_week()
    {
        return $this->belongsTo(TvdeWeek::class, 'tvde_week_id');
    }

    public function allocations()
    {
        return $this->hasMany(WeeklyVehicleMileageAllocation::class);
    }
}
