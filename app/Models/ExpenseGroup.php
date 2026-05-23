<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpenseGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'created_by',
        'avatar',
        'image_path',
        'currency',
        'permissions',
        'is_active',
        'archived_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'permissions' => 'array',
        'archived_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'group_id');
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class, 'group_id');
    }

    public function activity()
    {
        return $this->hasMany(GroupActivity::class, 'group_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }
}
