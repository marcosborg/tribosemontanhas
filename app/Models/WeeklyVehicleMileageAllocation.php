<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyVehicleMileageAllocation extends Model
{
    public $table = 'weekly_vehicle_mileage_allocations';

    protected $fillable = [
        'weekly_vehicle_expense_id',
        'driver_id',
        'allocated_km',
        'allowance_km',
        'extra_km',
        'is_manual',
    ];

    protected $casts = [
        'allocated_km' => 'decimal:2',
        'allowance_km' => 'decimal:2',
        'extra_km' => 'decimal:2',
        'is_manual' => 'boolean',
    ];

    public function weeklyVehicleExpense()
    {
        return $this->belongsTo(WeeklyVehicleExpense::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
