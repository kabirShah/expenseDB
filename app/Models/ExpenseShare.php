<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpenseShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'member_id',
        'share_amount',
        'amount_settled',
        'status'
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
