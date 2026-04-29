<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverDepositMovement extends Model
{
    use SoftDeletes, HasFactory;

    public const TYPE_INITIAL_CHARGE = 'initial_charge';
    public const TYPE_WEEKLY_CHARGE = 'weekly_charge';
    public const TYPE_INTERNAL_DEBIT = 'internal_debit';
    public const TYPE_REFUND = 'refund';

    public const TYPE_SELECT = [
        self::TYPE_INITIAL_CHARGE => 'Caucao - pagamento inicial',
        self::TYPE_WEEKLY_CHARGE => 'Caucao - pagamento semanal',
        self::TYPE_INTERNAL_DEBIT => 'Caucao - abatimento',
        self::TYPE_REFUND => 'Caucao - devolucao',
    ];

    public $table = 'driver_deposit_movements';

    protected $fillable = [
        'driver_deposit_id',
        'driver_id',
        'company_id',
        'tvde_week_id',
        'type',
        'description',
        'amount',
        'balance_after',
        'affects_statement',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'affects_statement' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function deposit()
    {
        return $this->belongsTo(DriverDeposit::class, 'driver_deposit_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function tvde_week()
    {
        return $this->belongsTo(TvdeWeek::class, 'tvde_week_id');
    }
}
