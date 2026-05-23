<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SplitwiseGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'name',
        'description',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SplitwiseGroupMember::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(SplitwiseExpense::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SplitwiseSettlement::class);
    }
}
