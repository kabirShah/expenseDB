<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_ai_generated',
        'usage_count',
    ];

    protected $casts = [
        'is_ai_generated' => 'boolean',
        'usage_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    // Relationships
    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(ExpenseCore::class, 'expense_categories');
    }

    // Scopes
    public function scopeAiGenerated($query)
    {
        return $query->where('is_ai_generated', true);
    }

    public function scopeManual($query)
    {
        return $query->where('is_ai_generated', false);
    }

    public function scopePopular($query)
    {
        return $query->orderBy('usage_count', 'desc');
    }

    // Helper methods
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    public function getDisplayName()
    {
        return $this->name;
    }

    public function getIconHtml()
    {
        return $this->icon ? "<i class='{$this->icon}'></i>" : '';
    }

    public function getColorStyle()
    {
        return $this->color ? "color: {$this->color};" : '';
    }
}
