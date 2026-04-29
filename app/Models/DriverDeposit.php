<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverDeposit extends Model
{
    use SoftDeletes, HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CLOSED = 'closed';

    public const STATUS_SELECT = [
        self::STATUS_ACTIVE => 'Ativa',
        self::STATUS_COMPLETED => 'Concluida',
        self::STATUS_CLOSED => 'Fechada',
    ];

    public $table = 'driver_deposits';

    protected $fillable = [
        'driver_id',
        'company_id',
        'total_amount',
        'initial_payment',
        'weekly_amount',
        'status',
        'notes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'initial_payment' => 'decimal:2',
        'weekly_amount' => 'decimal:2',
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

    public function movements()
    {
        return $this->hasMany(DriverDepositMovement::class, 'driver_deposit_id');
    }
}
