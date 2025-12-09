<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpenseContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'member_id',
        'amount_paid',
    ];

    public function expense()
    {
        return $this->belongsTo(GroupExpense::class, 'expense_id');
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }
}
