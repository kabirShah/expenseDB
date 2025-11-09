<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MultiExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'total_amount',
        'description',
        'category',
        'multi_expense_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relationship: each multi-expense belongs to one user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Automatically generate UUID before saving
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->multi_expense_id)) {
                $model->multi_expense_id = Str::uuid();
            }
        });
    }
}
