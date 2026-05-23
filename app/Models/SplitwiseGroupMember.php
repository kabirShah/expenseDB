<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SplitwiseGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'splitwise_group_id',
        'user_id',
        'name',
        'email',
        'role',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SplitwiseGroup::class, 'splitwise_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(SplitwiseExpenseSplit::class, 'member_id');
    }
}
