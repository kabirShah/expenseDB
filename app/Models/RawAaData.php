<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawAaData extends Model
{
    use HasFactory;

    protected $table = 'raw_aa_data';

    protected $fillable = [
        'user_id',
        'consent_id',
        'payload',
        'processed',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }
}
