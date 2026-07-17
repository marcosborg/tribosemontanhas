<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CompanyExpense extends Model implements HasMedia
{
    use SoftDeletes, HasFactory, InteractsWithMedia;

    public const MODE_RECURRING = 'recurring';
    public const MODE_ACCOUNTING = 'accounting';
    public const EXPENSE_TYPE_RADIO = VehicleExpense::EXPENSE_TYPE_RADIO;

    protected $appends = ['files'];

    public $table = 'company_expenses';

    protected $dates = [
        'start_date',
        'end_date',
        'date',
        'paid_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'name',
        'company_id',
        'expense_mode',
        'expense_type',
        'date',
        'description',
        'value',
        'invoice_value',
        'vat',
        'is_paid',
        'paid_at',
        'payment_reference',
        'pay_to',
        'weekly_value',
        'start_date',
        'end_date',
        'qty',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit('crop', 50, 50);
        $this->addMediaConversion('preview')->fit('crop', 120, 120);
    }

    public function getFilesAttribute()
    {
        return $this->getMedia('files');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function getStartDateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format(config('panel.date_format')) : null;
    }

    public function setStartDateAttribute($value)
    {
        $this->attributes['start_date'] = $value ? Carbon::createFromFormat(config('panel.date_format'), $value)->format('Y-m-d') : null;
    }

    public function getEndDateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format(config('panel.date_format')) : null;
    }

    public function setEndDateAttribute($value)
    {
        $this->attributes['end_date'] = $value ? Carbon::createFromFormat(config('panel.date_format'), $value)->format('Y-m-d') : null;
    }

    public function getDateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format(config('panel.date_format')) : null;
    }

    public function setDateAttribute($value)
    {
        $this->attributes['date'] = $value ? Carbon::createFromFormat(config('panel.date_format'), $value)->format('Y-m-d') : null;
    }

    public function getReportTotalAttribute(): float
    {
        return $this->expense_mode === self::MODE_ACCOUNTING
            ? (float) $this->value
            : (float) $this->qty * (float) $this->weekly_value;
    }

    public function scopeForPeriod($query, int $companyId, string $startDate, string $endDate)
    {
        return $query->where('company_id', $companyId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($query) use ($startDate, $endDate) {
                    $query->where('expense_mode', self::MODE_RECURRING)
                        ->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                })->orWhere(function ($query) use ($startDate, $endDate) {
                    $query->where('expense_mode', self::MODE_ACCOUNTING)
                        ->whereBetween('date', [$startDate, $endDate]);
                });
            });
    }
}
