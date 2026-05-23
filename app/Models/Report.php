<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'date_from',
        'date_to',
        'title',
        'file_path',
        'data_snapshot',
        'generated_at',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'generated_at' => 'datetime',
        'data_snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
