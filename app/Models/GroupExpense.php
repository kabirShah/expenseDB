<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'paid_by',
        'category_id',
        'title',
        'amount',
        'split_type',
        'notes',
        'receipt_image',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function paidByMember()
    {
        return $this->belongsTo(GroupMember::class, 'paid_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function splits()
    {
        return $this->hasMany(GroupExpenseSplit::class, 'group_expense_id');
    }
}
