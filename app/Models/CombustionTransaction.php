<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CombustionTransaction extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'combustion_transactions';

    protected $dates = [
        'transaction_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'tvde_week_id',
        'card',
        'amount',
        'total',
        'transaction_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getTransactionDateAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function setTransactionDateAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['transaction_date'] = null;
            return;
        }

        $formats = [
            'Y-m-d\TH:i',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                $this->attributes['transaction_date'] = Carbon::createFromFormat($format, (string) $value)
                    ->format('Y-m-d H:i:s');
                return;
            } catch (\Throwable $exception) {
                continue;
            }
        }

        $this->attributes['transaction_date'] = Carbon::parse((string) $value)->format('Y-m-d H:i:s');
    }

    public function tvde_week()
    {
        return $this->belongsTo(TvdeWeek::class, 'tvde_week_id');
    }

}
