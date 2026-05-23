<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',     // optional (UI)
        'color',    // optional (UI)
        'is_default'
    ];

    /**
     * Auto-generate slug (safe per user)
     */
    protected static function booted()
    {
        static::creating(function ($model) {

            if (empty($model->slug)) {

                $baseSlug = Str::slug($model->name);
                $slug = $baseSlug;
                $count = 1;

                // 🔥 Ensure unique per user
                while (self::where('user_id', $model->user_id)
                           ->where('slug', $slug)
                           ->exists()) {

                    $slug = $baseSlug . '-' . $count++;
                }

                $model->slug = $slug;
            }
        });
    }

    /**
     * Relationships
     */

    // Category belongs to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Category has many expenses
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    /**
     * Scope: user-specific categories
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}