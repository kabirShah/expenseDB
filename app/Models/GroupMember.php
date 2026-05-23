<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'name',
        'email',
        'phone',
        'role',
        'status',
        'invited_by',
        'permissions',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paidExpenses()
    {
        return $this->hasMany(Expense::class, 'user_id');
    }

    public function splits()
    {
        return $this->hasMany(ExpenseSplit::class, 'user_id', 'user_id');
    }

    public function settlementsAsPayer()
    {
        return $this->hasMany(Settlement::class, 'from_user_id', 'user_id');
    }

    public function settlementsAsPayee()
    {
        return $this->hasMany(Settlement::class, 'to_user_id', 'user_id');
    }
}
