<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'created_by',
        'name',
        'description',
        'currency',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->group_id)) {
                $model->group_id = Str::uuid();
            }
        });
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function expenseSplits(): HasMany
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function activeMembers()
    {
        return $this->members()->where('status', 'active');
    }

    public function isMember($userId)
    {
        return $this->members()->where('user_id', $userId)->where('status', 'active')->exists();
    }

    public function isAdmin($userId)
    {
        return $this->members()->where('user_id', $userId)->where('role', 'admin')->exists();
    }

    public function getTotalExpenses()
    {
        return $this->expenseSplits()->sum('total_amount');
    }

    public function getMemberCount()
    {
        return $this->members()->where('status', 'active')->count();
    }
}
