<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'type',
        'entity_id',
        'message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}