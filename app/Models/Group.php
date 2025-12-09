<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_uuid',
        'created_by',
        'name',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class);
    }

    public function expenses()
    {
        return $this->hasMany(GroupExpense::class);
    }
    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
