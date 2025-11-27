<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriversBalance extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'drivers_balances';

    protected $casts = [
        'value'   => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'driver_id',
        'tvde_week_id',
        'value',
        'balance',
        'drivers_balance',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function tvde_week()
    {
        return $this->belongsTo(TvdeWeek::class, 'tvde_week_id');
    }

    /**
     * Recalcula o saldo a partir de uma semana, aplicando um delta (positivo ou negativo)
     * apenas na primeira semana e propagando o carry para as seguintes.
     *
     * @param int $driverId
     * @param int $tvdeWeekId
     * @param float $delta       Valor a somar ao saldo da semana alvo (use negativo para abater).
     */
    public static function applyAdjustmentFromWeek(int $driverId, int $tvdeWeekId, float $delta = 0): void
    {
        $previousBalance = self::where('driver_id', $driverId)
            ->where('tvde_week_id', '<', $tvdeWeekId)
            ->orderBy('tvde_week_id', 'desc')
            ->value('balance');

        $running = (float) ($previousBalance ?? 0);

        $records = self::where('driver_id', $driverId)
            ->where('tvde_week_id', '>=', $tvdeWeekId)
            ->orderBy('tvde_week_id')
            ->get()
            ->values();

        foreach ($records as $index => $record) {
            $record->drivers_balance = $running;
            $record->balance = $running + (float) $record->value + ($index === 0 ? $delta : 0);
            $running = (float) $record->balance;
            $record->save();
        }
    }
}
