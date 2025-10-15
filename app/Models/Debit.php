<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'debit_card_id',
        'user_id',
        'card_number',
        'holder_name',
        'expiry_date',
    ];

    protected $hidden = ['id', 'user_id']; // optional: hide internal IDs
}
