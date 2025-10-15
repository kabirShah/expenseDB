<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Split extends Model
{
    use HasFactory;

    protected $fillable = [
        'split_expense_id',
        'user_id',
        'title',
        'total_amount',
        'participants',
    ];

    protected $casts = [
        'participants' => 'array', // auto convert JSON <-> array
    ];

    protected $hidden = ['id', 'user_id'];
}
