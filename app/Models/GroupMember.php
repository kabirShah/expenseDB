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
        'phone',
        'email',
        'is_app_user',
        'notification_preferences'
    ];

    protected $casts = [
        'notification_preferences' => 'array'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function contributions()
    {
        return $this->hasMany(ExpenseContribution::class, 'member_id');
    }

    public function shares()
    {
        return $this->hasMany(ExpenseShare::class, 'member_id');
    }
}
