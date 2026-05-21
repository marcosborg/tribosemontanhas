<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverDepositPlan extends Model
{
    use SoftDeletes, HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SELECT = [
        self::STATUS_ACTIVE => 'Ativo',
        self::STATUS_PAUSED => 'Pausado',
        self::STATUS_COMPLETED => 'Concluido',
    ];

    public $table = 'driver_deposit_plans';

    protected $fillable = [
        'driver_id',
        'company_id',
        'initial_amount',
        'weekly_amount',
        'total_weeks',
        'start_week_id',
        'status',
        'notes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'weekly_amount' => 'decimal:2',
        'total_weeks' => 'integer',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function start_week()
    {
        return $this->belongsTo(TvdeWeek::class, 'start_week_id');
    }

    public function items()
    {
        return $this->hasMany(DriverDepositPlanItem::class, 'plan_id');
    }
}
