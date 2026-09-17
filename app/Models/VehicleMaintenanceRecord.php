<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleMaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_maintenance_schedule_id',
        'vehicle_item_id',
        'type',
        'performed_km',
        'performed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'performed_km' => 'integer',
        'performed_at' => 'date',
    ];

    public function schedule()
    {
        return $this->belongsTo(VehicleMaintenanceSchedule::class, 'vehicle_maintenance_schedule_id');
    }

    public function vehicle_item()
    {
        return $this->belongsTo(VehicleItem::class, 'vehicle_item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
