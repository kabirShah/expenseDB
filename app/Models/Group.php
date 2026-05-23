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
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
        return $this->hasMany(GroupExpense::class, 'group_id');
    }

    public function settlements()
    {
        return $this->hasMany(GroupSettlement::class, 'group_id');
    }

    public function activity()
    {
        return $this->hasMany(GroupActivity::class, 'group_id');
    }
}
