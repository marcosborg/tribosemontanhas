<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDepositPlanItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_SELECT = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_PAID => 'Pago',
        self::STATUS_OVERDUE => 'Vencido',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public $table = 'driver_deposit_plan_items';

    protected $fillable = [
        'plan_id',
        'tvde_week_id',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'paid_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function plan()
    {
        return $this->belongsTo(DriverDepositPlan::class, 'plan_id');
    }

    public function tvde_week()
    {
        return $this->belongsTo(TvdeWeek::class, 'tvde_week_id');
    }

    public function movements()
    {
        return $this->hasMany(DriverDepositMovement::class, 'driver_deposit_plan_item_id');
    }
}
