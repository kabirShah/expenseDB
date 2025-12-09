<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_uuid',
        'group_id',
        'created_by',
        'title',
        'total_amount',
        'split_type',
        'date',
        'note',
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contributions()
    {
        return $this->hasMany(ExpenseContribution::class, 'expense_id');
    }

    public function shares()
    {
        return $this->hasMany(ExpenseShare::class, 'expense_id');
    }
}
