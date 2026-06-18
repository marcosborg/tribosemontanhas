<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PendingTask extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'pending_tasks';

    protected $fillable = [
        'title',
        'description',
        'due_date',
        'completed_at',
        'completed_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
