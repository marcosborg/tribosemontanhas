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

class VehicleExpense extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, HasFactory;

    protected $appends = [
        'files',
    ];

    public $table = 'vehicle_expenses';

    protected $dates = [
        'date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public const EXPENSE_TYPE_RADIO = [
        'Acessórios' => 'Acessórios',
        'Aquisição' => 'Aquisição',
        'Bate-chapa' => 'Bate-chapa',
        'Distico verde' => 'Distico verde',
        'Empréstimos' => 'Empréstimos',
        'Inspeção' => 'Inspeção',
        'IUC' => 'IUC',
        'Limpeza' => 'Limpeza',
        'Mecánica' => 'Mecánica',
        'Penus' => 'Pneus',
        'Seguro' => 'Seguro',
        'Outros' => 'Outros',
    ];


    public const NORMALIZED_TYPE_MAP = [
        // Maintenance
        'mecânica' => 'maintenance',
        'mecánica' => 'maintenance',
        'mecanica' => 'maintenance',
        'manutenção' => 'maintenance',
        'bate-chapa' => 'maintenance',
        'pneus' => 'maintenance',

        // Rent / Loans
        'empréstimos' => 'rent',
        'rent' => 'rent',

        // Acquisition
        'aquisição' => 'acquisition',
        'aquisição da viatura' => 'acquisition',

        // Other examples
        'seguro' => 'insurance',
        'iuc' => 'tax',
    ];

    protected $fillable = [
        'vehicle_item_id',
        'expense_type',
        'date',
        'description',
        'value',
        'invoice_value',
        'vat',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit('crop', 50, 50);
        $this->addMediaConversion('preview')->fit('crop', 120, 120);
    }

    public function getDateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format(config('panel.date_format')) : null;
    }

    public function setDateAttribute($value)
    {
        $this->attributes['date'] = $value ? Carbon::createFromFormat(config('panel.date_format'), $value)->format('Y-m-d') : null;
    }

    protected static function booted()
    {
        static::saving(function ($expense) {
            if (!$expense->expense_type) {
                $expense->normalized_type = 'other';
                return;
            }

            $key = mb_strtolower(trim($expense->expense_type));

            $expense->normalized_type =
                self::NORMALIZED_TYPE_MAP[$key] ?? 'other';
        });
    }

    public function getFilesAttribute()
    {
        return $this->getMedia('files');
    }

    public function vehicle_item()
    {
        return $this->belongsTo(VehicleItem::class, 'vehicle_item_id');
    }
}
